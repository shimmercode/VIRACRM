<?php

namespace Tests\Feature;

use Tests\ViraTestCase;

/**
 * A class name used with `::` that is neither imported nor fully qualified.
 *
 * THE DEFECT THIS EXISTS TO CATCH
 * -------------------------------
 * `StoreCustomerRequest` was edited to derive its status rule from the enum:
 *
 *     'status' => ['required', Rule::in(array_column(CustomerStatus::cases(), 'value'))],
 *
 * The `use Illuminate\Validation\Rule;` import was not added. PHP resolves the
 * unqualified name against the file's own namespace, so this became
 * `App\Http\Requests\Customer\Rule` — a class that does not exist. The result
 * was not a validation failure but a fatal `Error` on **every** customer
 * create: HTTP 500.
 *
 * It survived because the only tests that created a customer over HTTP asserted
 * `select id from customers order by id desc limit 1` was greater than zero,
 * which is true of the seeded rows whether or not the POST worked. A whole
 * module was down and the suite was green.
 *
 * WHY A STATIC SCAN AND NOT A SMOKE TEST PER FORM REQUEST
 * -------------------------------------------------------
 * Instantiating a FormRequest needs a bound container and a request, and most
 * `rules()` methods are protected, so a runtime check is either shallow or
 * needs reflection plumbing per class. The failure mode here is purely lexical:
 * a name that cannot resolve. Scanning for it is exact, needs no fixtures, and
 * covers every class in `app/` — including ones no test touches yet.
 *
 * Comments and strings are stripped first, so a `Rule::in` mentioned in prose
 * (as `HasDomainConstraints` does) is not a finding.
 */
final class StaticReferenceIntegrityTest extends ViraTestCase
{
    /** Language constructs that look like `Name::` but resolve locally. */
    private const LOCAL = ['self', 'static', 'parent'];

    public function run(): void
    {
        $this->everyStaticReferenceResolves();
        $this->theScannerDetectsAKnownBadReference();
    }

    private function everyStaticReferenceResolves(): void
    {
        $root = dirname(__DIR__, 2) . '/app';

        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        /*
         * Pass 1 — index every declared name by namespace.
         *
         * An unqualified `User::` inside `App\Models\Contract` is correct PHP:
         * it resolves to `App\Models\User`. Judging that without knowing what
         * the namespace contains produced twenty-odd false findings on the very
         * first run, which is the fastest way to get a useful check ignored.
         */
        $byNamespace = [];
        $parsed = [];

        foreach ($files as $path) {
            $code = $this->stripCommentsAndStrings((string) file_get_contents($path));
            $namespace = $this->namespaceOf($code);
            $declared = $this->declaredNames($code);

            foreach (array_keys($declared) as $name) {
                $byNamespace[$namespace][$name] = true;
            }

            $parsed[$path] = ['code' => $code, 'namespace' => $namespace, 'declared' => $declared];
        }

        // Pass 2 — resolve every `Name::` against import, file, or namespace.
        $offenders = [];

        foreach ($parsed as $path => $info) {
            $known = array_merge(
                $info['declared'],
                $this->importedAliases($info['code']),
                $byNamespace[$info['namespace']] ?? [],
                // A name with no namespace of its own resolves in the global
                // scope, where the engine's own classes live.
                $byNamespace[''] ?? [],
            );

            preg_match_all('/(?<![\w\\\\$>])([A-Z][A-Za-z0-9_]*)::/', $info['code'], $m);

            foreach (array_unique($m[1]) as $name) {
                if (in_array(strtolower($name), self::LOCAL, true)) {
                    continue;
                }

                if (! isset($known[$name])) {
                    $offenders[] = str_replace(dirname(__DIR__, 2) . '/', '', $path)
                        . ' → ' . $name . '::';
                }
            }
        }

        $this->assertGreaterThan(20, count($files), 'the scan must actually walk app/');

        $this->assertSame(
            [],
            $offenders,
            'Unresolvable static references (a class used with :: that is neither '
            . "imported, nor declared in the file, nor in its namespace):\n  - "
            . implode("\n  - ", $offenders)
            . "\nEach of these is a fatal Error at runtime, not a warning.",
        );
    }

    /** The file's namespace, or '' when it declares none. */
    private function namespaceOf(string $code): string
    {
        return preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/m', $code, $m) === 1
            ? $m[1]
            : '';
    }

    /**
     * The scanner must actually catch the defect it was written for.
     *
     * A static check that passes on the current tree proves nothing on its own
     * — it could be matching nothing at all. This feeds it the exact shape of
     * the `StoreCustomerRequest` bug and asserts it is reported.
     */
    private function theScannerDetectsAKnownBadReference(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Http\Requests\Customer;
        use App\Enums\CustomerType;
        class Demo {
            public function rules() {
                return ['status' => Rule::in(CustomerType::cases())];
            }
        }
        PHP;

        $known = array_merge($this->importedAliases($code), $this->declaredNames($code));

        $this->assertTrue(isset($known['CustomerType']), 'an imported class is recognised');
        $this->assertTrue(isset($known['Demo']), 'a class declared in the file is recognised');
        $this->assertFalse(isset($known['Rule']), 'the unimported Rule must NOT be recognised');
    }

    /**
     * Remove comments and quoted text so prose cannot be mistaken for code.
     *
     * Quoted bodies are replaced with an empty string rather than deleted, so
     * concatenation boundaries stay intact and no accidental `::` is created by
     * joining the surrounding fragments.
     */
    private function stripCommentsAndStrings(string $source): string
    {
        $patterns = [
            '#/\*.*?\*/#s',        // block and doc comments
            '#//[^\n]*#',          // line comments
            "#<<<'?([A-Z]+)'?\\n.*?\\n\\1;#s", // heredoc / nowdoc
            "#'(?:\\\\.|[^'\\\\])*'#s",
            '#"(?:\\\\.|[^"\\\\])*"#s',
        ];

        return (string) preg_replace($patterns, "''", $source);
    }

    /** @return array<string, true> short alias => true */
    private function importedAliases(string $code): array
    {
        $out = [];

        // `use A\B\C;` and `use A\B\C as D;`, including grouped `use A\{B, C};`
        if (preg_match_all('/^\s*use\s+(?:function\s+|const\s+)?([^;]+);/m', $code, $m)) {
            foreach ($m[1] as $clause) {
                foreach (explode(',', $clause) as $part) {
                    $part = trim($part);

                    if ($part === '') {
                        continue;
                    }

                    if (preg_match('/\s+as\s+(\w+)$/i', $part, $alias)) {
                        $out[$alias[1]] = true;

                        continue;
                    }

                    $segments = explode('\\', $part);
                    $out[end($segments)] = true;
                }
            }
        }

        return $out;
    }

    /** @return array<string, true> names declared in this very file */
    private function declaredNames(string $code): array
    {
        $out = [];

        if (preg_match_all('/\b(?:class|interface|trait|enum)\s+(\w+)/', $code, $m)) {
            foreach ($m[1] as $name) {
                $out[$name] = true;
            }
        }

        return $out;
    }
}
