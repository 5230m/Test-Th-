<?php
/**
 * Plugin Name: Domain Processor
 * Description: Domain Processor — Tách root domain, full domain, thêm https://
 * Version: 1.0.1
 * Author: Hanax
 *
 * Shortcode: [hanax_domain_processor]
 * Requires: public_suffix_list.dat in same directory as this file
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function hanax_domain_processor_shortcode() {
    $psl_url = home_url( '/Tools%20L%E1%BA%BB/public_suffix_list.dat' );
    ob_start();
    ?>
    <div id="hanax-dt-wrap" class="seo-tool-wrapper">
        <div class="seo-tool-input-area">
            <textarea
                id="hdt-input"
                class="seo-tool-textarea"
                style="min-height:160px"
                placeholder="Dán danh sách URL — mỗi dòng 1 URL"></textarea>
            <div id="hdt-psl-status" class="seo-tool-textarea-note">Đang tải Public Suffix List...</div>

            <div class="seo-tool-controls" style="margin-top:12px">
                <div class="seo-tool-actions">
                    <button class="seo-tool-btn" onclick="hdtProcess('root-icann')">Root ICANN</button>
                    <button class="seo-tool-btn" onclick="hdtProcess('root-private')">Root Private</button>
                    <button class="seo-tool-btn" onclick="hdtProcess('full')">Tách Full Domain</button>
                    <button class="seo-tool-btn" onclick="hdtProcess('https')">Thêm https://</button>
                    <button class="seo-tool-btn-secondary" onclick="hdtClear()">Xoá</button>
                </div>
            </div>

            <div style="margin-top:16px">
                <div style="display:flex;align-items:center;margin-bottom:6px">
                    <span class="seo-tool-label" id="hdt-output-label">Kết quả</span>
                    <span id="hdt-status" class="seo-tool-textarea-note" style="margin-left:auto;margin-right:10px;margin-top:0"></span>
                    <button class="seo-tool-btn-secondary" onclick="hdtCopy()" id="hdt-copy-btn" style="display:none">Copy</button>
                </div>
                <textarea
                    id="hdt-output"
                    class="seo-tool-textarea"
                    style="min-height:160px"
                    readonly
                    placeholder="Kết quả sẽ hiện ở đây..."></textarea>
            </div>

            <div id="hdt-filter-wrap" style="display:none;margin-top:20px;border-top:1px solid #d1d9e0;padding-top:16px">
                <div class="seo-tool-label" style="text-transform:uppercase;letter-spacing:.3px;margin-bottom:10px;font-weight:700">Filter kết quả</div>

                <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
                    <div>
                        <div class="seo-tool-label" style="font-weight:400">Số ký tự label (min – max)</div>
                        <div style="display:flex;align-items:center;gap:6px">
                            <input type="number" id="hdt-char-min" class="seo-tool-api-input" style="width:70px" placeholder="Min" min="1">
                            <span>–</span>
                            <input type="number" id="hdt-char-max" class="seo-tool-api-input" style="width:70px" placeholder="Max" min="1">
                        </div>
                    </div>
                    <div style="flex:1;min-width:200px">
                        <div class="seo-tool-label" style="font-weight:400">Chứa từ khoá (AND, mỗi dòng 1 từ)</div>
                        <textarea id="hdt-kw-input" class="seo-tool-textarea" style="min-height:60px" placeholder="Nhập từ khoá..."></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:8px;margin-top:10px;align-items:center">
                    <button class="seo-tool-btn" onclick="hdtFilter()">Lọc</button>
                    <button class="seo-tool-btn-secondary" onclick="hdtResetFilter()">Reset filter</button>
                    <span id="hdt-filter-status" class="seo-tool-textarea-note" style="margin-top:0;margin-left:4px"></span>
                    <button class="seo-tool-btn-secondary" onclick="hdtCopyFiltered()" id="hdt-copy-filtered-btn" style="display:none;margin-left:auto">Copy kết quả lọc</button>
                </div>

                <textarea
                    id="hdt-filtered"
                    class="seo-tool-textarea"
                    style="min-height:140px;margin-top:10px"
                    readonly
                    placeholder="Kết quả sau lọc sẽ hiện ở đây..."></textarea>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const PSL_URL = <?php echo json_encode( $psl_url ); ?>;
        let icannRules = null, icannExceptions = null, fullRules = null, fullExceptions = null;

        fetch(PSL_URL)
            .then(r => r.text())
            .then(text => {
                parsePSL(text);
                document.getElementById('hdt-psl-status').textContent = '✓ Public Suffix List đã tải xong';
                setTimeout(() => { document.getElementById('hdt-psl-status').style.display = 'none'; }, 2000);
            })
            .catch(() => {
                document.getElementById('hdt-psl-status').textContent = '⚠ Không tải được PSL — tính năng tách domain sẽ kém chính xác hơn';
            });

        function parsePSL(text) {
            icannRules = new Set(); icannExceptions = new Set();
            fullRules  = new Set(); fullExceptions  = new Set();
            const lines = text.split('\n');
            let inIcann = false;
            for (let line of lines) {
                if (line.includes('===BEGIN ICANN DOMAINS===')) { inIcann = true; continue; }
                if (line.includes('===END ICANN DOMAINS==='))   { inIcann = false; continue; }
                const trimmed = line.trim();
                if (!trimmed || trimmed.startsWith('//')) continue;
                if (trimmed.startsWith('!')) {
                    fullExceptions.add(trimmed.slice(1).toLowerCase());
                } else {
                    fullRules.add(trimmed.toLowerCase());
                }
                if (inIcann) {
                    if (trimmed.startsWith('!')) icannExceptions.add(trimmed.slice(1).toLowerCase());
                    else icannRules.add(trimmed.toLowerCase());
                }
            }
        }

        function getRegistrableDomain(hostname, rules, exceptions) {
            hostname = hostname.toLowerCase().replace(/^www\./, '');
            const labels = hostname.split('.');
            if (!rules) return labels.slice(-2).join('.');
            for (let i = 0; i < labels.length - 1; i++) {
                const candidate = labels.slice(i).join('.');
                if (exceptions.has(candidate)) return labels.slice(i).join('.');
            }
            let matchLen = 0;
            for (let i = 0; i < labels.length - 1; i++) {
                const candidate = labels.slice(i).join('.');
                const wildcard  = '*.' + labels.slice(i + 1).join('.');
                if (rules.has(candidate) || rules.has(wildcard)) {
                    const ruleLen = labels.length - i;
                    if (ruleLen > matchLen) matchLen = ruleLen;
                }
            }
            if (matchLen === 0) matchLen = 1;
            const suffixStart = labels.length - matchLen;
            if (suffixStart === 0) return hostname;
            return labels.slice(suffixStart - 1).join('.');
        }

        function getHostname(raw) {
            raw = raw.trim();
            if (!raw) return '';
            if (!/^https?:\/\//i.test(raw)) raw = 'https://' + raw;
            try { return new URL(raw).hostname.toLowerCase(); }
            catch(e) { return raw.replace(/^https?:\/\//i, '').split('/')[0].split('?')[0].split('#')[0].toLowerCase(); }
        }

        function ensureHttps(raw) {
            raw = raw.trim();
            if (!raw) return '';
            if (/^https:\/\//i.test(raw)) return raw;
            if (/^http:\/\//i.test(raw)) return 'https://' + raw.slice(7);
            return 'https://' + raw;
        }

        window.hdtProcess = function(mode) {
            const input = document.getElementById('hdt-input').value;
            const lines = input.split('\n').map(l => l.trim()).filter(l => l);
            if (!lines.length) { alert('Vui lòng nhập danh sách URL trước.'); return; }
            const results = lines.map(line => {
                if (mode === 'root-icann')   return getRegistrableDomain(getHostname(line), icannRules, icannExceptions) || line;
                if (mode === 'root-private') return getRegistrableDomain(getHostname(line), fullRules, fullExceptions) || line;
                if (mode === 'full')         return getHostname(line) || line;
                if (mode === 'https')        return ensureHttps(line);
                return line;
            });
            const labels = { 'root-icann':'Root ICANN', 'root-private':'Root Private', 'full':'Full Domain', 'https':'Thêm https://' };
            document.getElementById('hdt-output').value = results.join('\n');
            document.getElementById('hdt-output-label').textContent = 'Kết quả — ' + labels[mode];
            document.getElementById('hdt-status').textContent = results.length + ' dòng';
            document.getElementById('hdt-copy-btn').style.display = '';
            const isRoot = mode === 'root-icann' || mode === 'root-private';
            document.getElementById('hdt-filter-wrap').style.display = isRoot ? '' : 'none';
            document.getElementById('hdt-filtered').value = '';
            document.getElementById('hdt-filter-status').textContent = '';
            document.getElementById('hdt-copy-filtered-btn').style.display = 'none';
        };

        window.hdtFilter = function() {
            const lines = document.getElementById('hdt-output').value.split('\n').map(l => l.trim()).filter(l => l);
            if (!lines.length) return;
            const min = document.getElementById('hdt-char-min').value.trim();
            const max = document.getElementById('hdt-char-max').value.trim();
            const minN = min !== '' ? parseInt(min) : null;
            const maxN = max !== '' ? parseInt(max) : null;
            const keywords = document.getElementById('hdt-kw-input').value.split('\n').map(k => k.trim().toLowerCase()).filter(k => k);
            const filtered = lines.filter(domain => {
                const len = domain.split('.')[0].length;
                if (minN !== null && len < minN) return false;
                if (maxN !== null && len > maxN) return false;
                for (const kw of keywords) { if (!domain.toLowerCase().includes(kw)) return false; }
                return true;
            });
            document.getElementById('hdt-filtered').value = filtered.join('\n');
            document.getElementById('hdt-filter-status').textContent = filtered.length + ' / ' + lines.length + ' domain';
            document.getElementById('hdt-copy-filtered-btn').style.display = '';
        };

        window.hdtResetFilter = function() {
            document.getElementById('hdt-char-min').value = '';
            document.getElementById('hdt-char-max').value = '';
            document.getElementById('hdt-kw-input').value = '';
            document.getElementById('hdt-filtered').value = '';
            document.getElementById('hdt-filter-status').textContent = '';
            document.getElementById('hdt-copy-filtered-btn').style.display = 'none';
        };

        window.hdtCopyFiltered = function() {
            const el = document.getElementById('hdt-filtered');
            el.select(); document.execCommand('copy');
            const btn = document.getElementById('hdt-copy-filtered-btn');
            const orig = btn.textContent; btn.textContent = 'Đã copy!';
            setTimeout(() => btn.textContent = orig, 1500);
        };

        window.hdtCopy = function() {
            const output = document.getElementById('hdt-output');
            output.select(); document.execCommand('copy');
            const btn = document.getElementById('hdt-copy-btn');
            const orig = btn.textContent; btn.textContent = 'Đã copy!';
            setTimeout(() => btn.textContent = orig, 1500);
        };

        window.hdtClear = function() {
            document.getElementById('hdt-input').value = '';
            document.getElementById('hdt-output').value = '';
            document.getElementById('hdt-status').textContent = '';
            document.getElementById('hdt-copy-btn').style.display = 'none';
            document.getElementById('hdt-filter-wrap').style.display = 'none';
            document.getElementById('hdt-filtered').value = '';
            document.getElementById('hdt-filter-status').textContent = '';
            document.getElementById('hdt-copy-filtered-btn').style.display = 'none';
        };
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'hanax_domain_processor', 'hanax_domain_processor_shortcode' );
