/**
 * Minimal TrueType metrics reader — advance widths only.
 * ------------------------------------------------------
 * Needed because the WASM Graphviz build has no fontconfig: it cannot see Vazirmatn,
 * so it lays out table cells with a fallback metric. To stop text from being clipped we
 * must know the REAL advance width of each string and pin the cell width ourselves.
 *
 * This reads the 'cmap' (format 4) and 'hmtx'/'hhea'/'head' tables directly. It supports
 * what Vazirmatn needs: BMP Unicode, format-4 cmap, non-monospaced hmtx. No shaping —
 * Persian shaping in this font does not change advance widths for the strings we measure
 * (no ligature substitution alters advances in Vazirmatn's Arabic coverage for these labels),
 * and we additionally add a safety margin.
 */

import fs from 'node:fs';

function readTables(buf) {
    const numTables = buf.readUInt16BE(4);
    const tables = {};
    for (let i = 0; i < numTables; i++) {
        const off = 12 + i * 16;
        const tag = buf.toString('ascii', off, off + 4);
        tables[tag] = { offset: buf.readUInt32BE(off + 8), length: buf.readUInt32BE(off + 12) };
    }
    return tables;
}

export function loadFontMetrics(file) {
    const buf = fs.readFileSync(file);
    const tables = readTables(buf);

    const head = tables.head.offset;
    const unitsPerEm = buf.readUInt16BE(head + 18);
    const indexToLocFormat = buf.readInt16BE(head + 50);

    const hhea = tables.hhea.offset;
    const numberOfHMetrics = buf.readUInt16BE(hhea + 34);

    const cmap = tables.cmap.offset;
    const cmapCount = buf.readUInt16BE(cmap + 2);
    let format4 = null;
    for (let i = 0; i < cmapCount; i++) {
        const rec = cmap + 4 + i * 8;
        const platform = buf.readUInt16BE(rec);
        const encoding = buf.readUInt16BE(rec + 2);
        const sub = cmap + buf.readUInt32BE(rec + 4);
        const format = buf.readUInt16BE(sub);
        if (format === 4 && (platform === 3 ? encoding === 1 || encoding === 10 : true)) {
            if (!format4) format4 = sub;
        }
    }
    if (!format4) throw new Error(`no format-4 cmap in ${file}`);

    const segX2 = buf.readUInt16BE(format4 + 6);
    const segCount = segX2 / 2;
    const endCodes = format4 + 14;
    const startCodes = endCodes + segX2 + 2;
    const idDeltas = startCodes + segX2;
    const idRangeOffsets = idDeltas + segX2;
    const glyphIdArray = idRangeOffsets + segX2;

    function glyphIndex(cp) {
        for (let s = 0; s < segCount; s++) {
            const end = buf.readUInt16BE(endCodes + s * 2);
            if (cp > end) continue;
            const start = buf.readUInt16BE(startCodes + s * 2);
            if (cp < start) return 0;
            const delta = buf.readInt16BE(idDeltas + s * 2);
            const ro = buf.readUInt16BE(idRangeOffsets + s * 2);
            if (ro === 0) return (cp + delta) & 0xffff;
            const addr = idRangeOffsets + s * 2 + ro + (cp - start) * 2;
            const g = buf.readUInt16BE(addr);
            return g === 0 ? 0 : (g + delta) & 0xffff;
        }
        return 0;
    }

    function advance(gid) {
        const idx = Math.min(gid, numberOfHMetrics - 1);
        return buf.readUInt16BE(tables.hmtx.offset + idx * 4);
    }

    /** Width of `text` at `pt`, in the same units Graphviz uses. */
    function measure(text, pt, letterSpacing = 0) {
        let units = 0;
        for (const ch of text) {
            const cp = ch.codePointAt(0);
            units += advance(glyphIndex(cp > 0xffff ? 0 : cp));
        }
        const ptWidth = (units / unitsPerEm) * pt;
        return ptWidth + [...text].length * letterSpacing;
    }

    return { measure, unitsPerEm };
}
