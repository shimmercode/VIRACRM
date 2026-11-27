<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PayrollRuleRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollRule;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * §15 / REQ-15.01 — the payroll rule list.
 *
 * A rule may only be used to compute pay once it is both active and approved
 * (`PayrollRule::isUsable()`), so approving one is a deliberate act and is
 * gated on `payroll.approve` rather than on `settings.manage`: the person who
 * maintains configuration is not automatically the person who authorises how
 * salaries are computed.
 *
 * No formula is created here — see PayrollRuleRequest for why.
 */
final class PayrollRuleController extends Controller
{
    public function index(): View
    {
        return view('settings.payroll-rules.index', [
            'rules' => PayrollRule::query()
                ->with('approvedBy:id,name')
                ->orderBy('code')
                ->get()
                ->map(function (PayrollRule $rule) {
                    $rule->periods_count = PayrollPeriod::query()
                        ->where('payroll_rule_id', $rule->id)
                        ->count();

                    return $rule;
                }),
            'methods' => PayrollRule::METHODS,
        ]);
    }

    public function create(): View
    {
        return view('settings.payroll-rules.create', [
            'rule' => new PayrollRule(['method' => PayrollRule::METHOD_MANUAL, 'is_active' => true]),
            'methods' => PayrollRule::METHODS,
            'canApprove' => auth()->user()->hasPermission('payroll.approve'),
        ]);
    }

    public function store(PayrollRuleRequest $request): RedirectResponse
    {
        $data = $request->validatedForModel();

        // Approval cannot be self-granted through the create form by someone who
        // does not hold payroll.approve.
        if ($data['is_approved'] && ! $request->user()->hasPermission('payroll.approve')) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['is_approved' => 'تأیید قاعدهٔ حقوق نیازمند مجوز «تأیید حقوق» است.']);
        }

        $rule = PayrollRule::query()->create([
            ...$data,
            'approved_by' => $data['is_approved'] ? $request->user()->id : null,
            'approved_at' => $data['is_approved'] ? now() : null,
        ]);

        AuditRecorder::created(
            $rule,
            sprintf('ثبت قاعدهٔ حقوق «%s»%s', $rule->title, $rule->is_approved ? ' (تأییدشده)' : ' (در انتظار تأیید)'),
        );

        return redirect()
            ->route('settings.payroll-rules.index')
            ->with('success', 'قاعدهٔ حقوق ثبت شد.');
    }

    public function edit(PayrollRule $payrollRule): View
    {
        $payrollRule->periods_count = PayrollPeriod::query()->where('payroll_rule_id', $payrollRule->id)->count();

        return view('settings.payroll-rules.edit', [
            'rule' => $payrollRule,
            'methods' => PayrollRule::METHODS,
            'canApprove' => auth()->user()->hasPermission('payroll.approve'),
        ]);
    }

    public function update(PayrollRuleRequest $request, PayrollRule $payrollRule): RedirectResponse
    {
        $data = $request->validatedForModel();
        $approving = ! $payrollRule->is_approved && $data['is_approved'];
        $revoking = $payrollRule->is_approved && ! $data['is_approved'];

        if (($approving || $revoking) && ! $request->user()->hasPermission('payroll.approve')) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['is_approved' => 'تغییر وضعیت تأیید نیازمند مجوز «تأیید حقوق» است.']);
        }

        /*
         * A rule already stamped on periods is the record of how those
         * payslips were computed. Its method must not change under them.
         */
        $inUse = (int) ($payrollRule->periods_count ?? 0);

        if ($inUse > 0 && $data['method'] !== $payrollRule->method) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['method' => sprintf(
                    '%d دورهٔ حقوق با این قاعده محاسبه شده است؛ روش آن قابل تغییر نیست.',
                    $inUse,
                )]);
        }

        $payrollRule->fill([
            ...$data,
            'approved_by' => $approving ? $request->user()->id : $payrollRule->approved_by,
            'approved_at' => $approving ? now() : ($revoking ? null : $payrollRule->approved_at),
        ])->save();

        AuditRecorder::updated($payrollRule, $payrollRule->getChanges(), 'ویرایش قاعدهٔ حقوق: ' . $payrollRule->title);

        return redirect()
            ->route('settings.payroll-rules.index')
            ->with('success', 'قاعدهٔ حقوق به‌روزرسانی شد.');
    }

    public function destroy(PayrollRule $payrollRule): RedirectResponse
    {
        $inUse = PayrollPeriod::query()->where('payroll_rule_id', $payrollRule->id)->count();

        if ($inUse > 0) {
            AuditRecorder::record(
                AuditRecorder::UPDATED,
                $payrollRule,
                sprintf('حذف قاعدهٔ حقوق «%s» رد شد: %d دوره با آن محاسبه شده است', $payrollRule->title, $inUse),
                ['refused' => 'in_use', 'periods' => $inUse],
            );

            return redirect()
                ->route('settings.payroll-rules.index')
                ->with('error', sprintf(
                    '%d دورهٔ حقوق با این قاعده محاسبه شده است؛ به‌جای حذف، آن را غیرفعال کنید.',
                    $inUse,
                ));
        }

        $title = $payrollRule->title;
        $payrollRule->delete();

        AuditRecorder::deleted($payrollRule, 'حذف قاعدهٔ حقوق: ' . $title);

        return redirect()
            ->route('settings.payroll-rules.index')
            ->with('success', 'قاعدهٔ حقوق حذف شد.');
    }
}
