
    (function () {
        var root = document.querySelector('.lm-settings-wrapper');
        if (!root) return;
        var isKhmer = document.body.classList.contains('lm-language-km');
        function text(en, km) { return isKhmer ? km : en; }
        // Tab Switching Mechanism
        var navItems = document.querySelectorAll('.lm-nav-item[data-tab]');
        var tabPanes = document.querySelectorAll('.lm-tab-pane');
        var activeTabInput = document.getElementById('activeTabInput');

        function switchTab(tabId) {
            var target = document.getElementById(tabId);
            if (!target || !target.classList.contains('lm-tab-pane')) return;
            navItems.forEach(function (item) {
                if (item.getAttribute('data-tab') === tabId) {
                    item.classList.add('active');
                    item.setAttribute('aria-selected', 'true');
                    item.tabIndex = 0;
                } else {
                    item.classList.remove('active');
                    item.setAttribute('aria-selected', 'false');
                    item.tabIndex = -1;
                }
            });

            tabPanes.forEach(function (pane) {
                if (pane.id === tabId) {
                    pane.classList.add('active');
                } else {
                    pane.classList.remove('active');
                }
            });

            if (activeTabInput) {
                activeTabInput.value = tabId;
            }

            if (history.replaceState) {
                history.replaceState(null, null, '#' + tabId);
            }
        }

        navItems.forEach(function (item, index) {
            item.id = 'settings-nav-' + item.dataset.tab;
            item.setAttribute('role', 'tab');
            item.setAttribute('aria-controls', item.dataset.tab);
            item.addEventListener('keydown', function (event) {
                var next = index;
                if (event.key === 'ArrowDown' || event.key === 'ArrowRight') next = (index + 1) % navItems.length;
                else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') next = (index + navItems.length - 1) % navItems.length;
                else if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = navItems.length - 1;
                else return;
                event.preventDefault();
                navItems[next].focus();
                switchTab(navItems[next].dataset.tab);
            });
            item.addEventListener('click', function (e) {
                var tabId = this.getAttribute('data-tab');
                if (tabId && document.getElementById(tabId)) {
                    e.preventDefault();
                    switchTab(tabId);
                }
            });
        });

        if (navItems[0]) { navItems[0].parentElement.setAttribute('role', 'tablist'); navItems[0].parentElement.setAttribute('aria-label', 'Settings sections'); }
        tabPanes.forEach(function (pane) {
            pane.setAttribute('role', 'tabpanel');
            pane.setAttribute('aria-labelledby', 'settings-nav-' + pane.id);
        });
        // Restore hash on load
        var currentHash = window.location.hash ? window.location.hash.replace('#', '') : '';
        var requestedTab = currentHash || (activeTabInput && activeTabInput.value) || 'tab-profile';
        switchTab('tab-profile');
        switchTab(requestedTab);
        window.addEventListener('hashchange', function () { switchTab(window.location.hash.slice(1)); });

        // Live Preview Bindings
        var businessInput = document.getElementById('businessNameInput');
        var systemInput = document.getElementById('systemNameInput');
        var subtitleInput = document.getElementById('systemSubtitleInput');
        var defaultInterestInput = document.getElementById('defaultInterestRateInput');
        var hiddenProfitPercent = document.getElementById('hiddenDefaultProfitPercent');
        var colorInput = document.getElementById('themeColorInput');
        var colorPicker = document.getElementById('themeColorPicker');
        var logoInput = document.getElementById('logoInput');
        var stampInput = document.getElementById('stampInput');
        var loginBackgroundInput = document.getElementById('loginBackgroundInput');
        var invoiceTemplateInput = document.getElementById('invoiceMessageTemplateInput');
        var invoiceTemplatePreview = document.getElementById('invoiceMessageTemplatePreview');
        var currencyCodeInput = document.getElementById('currencyCodeInput');
        var currencySymbolInput = document.getElementById('currencySymbolInput');
        var penaltyTypeInput = document.getElementById('penaltyTypeInput');
        var penaltyUnitLabel = document.getElementById('penaltyUnitLabel');

        var previewBusiness = document.getElementById('previewBusinessName');
        var previewSystem = document.getElementById('previewSystemName');
        var backgroundPreviewBusinessName = document.getElementById('backgroundPreviewBusinessName');
        var logoPreviewBox = document.getElementById('logoPreviewBox');
        var sidebarLogoPreview = document.getElementById('sidebarLogoPreview');
        var stampPreviewBox = document.getElementById('stampPreviewBox');
        var loginBackgroundPreview = document.getElementById('loginBackgroundPreview');

        function hexToRgb(color) {
            var normalized = color.replace('#', '');
            return [
                parseInt(normalized.substring(0, 2), 16),
                parseInt(normalized.substring(2, 4), 16),
                parseInt(normalized.substring(4, 6), 16)
            ];
        }

        function rgbToHex(r, g, b) {
            return '#' + [r, g, b].map(function (value) {
                var hex = Math.max(0, Math.min(255, Math.round(value))).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }).join('');
        }

        function mixWithWhite(rgb, ratio) {
            return rgbToHex(
                rgb[0] * (1 - ratio) + 255 * ratio,
                rgb[1] * (1 - ratio) + 255 * ratio,
                rgb[2] * (1 - ratio) + 255 * ratio
            );
        }

        function setPreviewColor(color) {
            if (!/^#[0-9A-Fa-f]{6}$/.test(color || '')) {
                return;
            }
            var rgb = hexToRgb(color);
            document.documentElement.style.setProperty('--lm-primary', color);
            document.documentElement.style.setProperty('--lm-primary-dark', rgbToHex(rgb[0] * .82, rgb[1] * .82, rgb[2] * .82));
            document.documentElement.style.setProperty('--lm-primary-light', mixWithWhite(rgb, .25));
            document.documentElement.style.setProperty('--lm-primary-50', mixWithWhite(rgb, .92));
            document.documentElement.style.setProperty('--lm-primary-100', mixWithWhite(rgb, .84));
            document.documentElement.style.setProperty('--lm-primary-200', mixWithWhite(rgb, .70));
            document.documentElement.style.setProperty('--lm-primary-rgb', rgb.join(', '));
            document.documentElement.style.setProperty('--lm-sidebar-active', color);
        }

        function syncPreview() {
            var bName = (businessInput && businessInput.value) || 'Loan Management';
            var sName = (systemInput && systemInput.value) || 'Loan Management';

            if (previewBusiness) previewBusiness.textContent = bName;
            if (previewSystem) previewSystem.textContent = sName;
            if (backgroundPreviewBusinessName) backgroundPreviewBusinessName.textContent = bName;

            if (defaultInterestInput && hiddenProfitPercent) {
                hiddenProfitPercent.value = defaultInterestInput.value;
            }

            if (invoiceTemplatePreview && invoiceTemplateInput) {
                invoiceTemplatePreview.textContent = (invoiceTemplateInput.value || '')
                    .split('{Customer Name}').join('Sok San (អតិថិជនគំរូ)')
                    .split('{Business Name}').join(bName);
            }

            if (colorInput) {
                setPreviewColor(colorInput.value);
            }
        }

        [businessInput, systemInput, subtitleInput, defaultInterestInput, colorInput, invoiceTemplateInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', syncPreview);
            }
        });

        // Insert tags into receipt template
        document.querySelectorAll('.insert-tag').forEach(function (button) {
            button.addEventListener('click', function () {
                var tag = this.getAttribute('data-tag');
                if (invoiceTemplateInput) {
                    var start = invoiceTemplateInput.selectionStart;
                    var end = invoiceTemplateInput.selectionEnd;
                    var text = invoiceTemplateInput.value;
                    invoiceTemplateInput.value = text.substring(0, start) + tag + text.substring(end);
                    invoiceTemplateInput.focus();
                    invoiceTemplateInput.selectionStart = invoiceTemplateInput.selectionEnd = start + tag.length;
                    invoiceTemplateInput.dispatchEvent(new Event('input', { bubbles: true }));
                    syncPreview();
                }
            });
        });

        // Penalty type change handler
        if (penaltyTypeInput && penaltyUnitLabel) {
            penaltyTypeInput.addEventListener('change', function () {
                penaltyUnitLabel.textContent = this.value === 'percentage' ? '% / day' : 'Fixed Fee (' + (currencySymbolInput ? currencySymbolInput.value : '$') + ')';
            });
        }

        // Currency select
        if (currencyCodeInput && currencySymbolInput) {
            currencyCodeInput.addEventListener('change', function () {
                var selected = currencyCodeInput.options[currencyCodeInput.selectedIndex];
                if (selected && selected.getAttribute('data-symbol')) {
                    currencySymbolInput.value = selected.getAttribute('data-symbol');
                    if (penaltyTypeInput && penaltyTypeInput.value === 'fixed') {
                        penaltyUnitLabel.textContent = 'Fixed Fee (' + currencySymbolInput.value + ')';
                    }
                }
            });
        }

        // Color swatches & picker
        if (colorPicker && colorInput) {
            colorPicker.addEventListener('input', function () {
                colorInput.value = colorPicker.value;
                colorInput.dispatchEvent(new Event('input', { bubbles: true }));
                syncPreview();
            });
            colorInput.addEventListener('input', function () {
                if (/^#[0-9A-Fa-f]{6}$/.test(colorInput.value)) {
                    colorPicker.value = colorInput.value;
                }
            });
        }
        document.querySelectorAll('.lm-swatch-circle').forEach(function (button) {
            button.addEventListener('click', function () {
                var col = this.getAttribute('data-color');
                if (colorInput) { colorInput.value = col; colorInput.dispatchEvent(new Event('input', { bubbles: true })); }
                if (colorPicker) colorPicker.value = col;
                syncPreview();
            });
        });

        // Instant image previews
        if (logoInput && logoPreviewBox) {
            logoInput.addEventListener('change', function () {
                var file = logoInput.files && logoInput.files[0];
                if (file && file.type.match(/^image\//)) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var img = '<img src="' + e.target.result + '" alt="">';
                        logoPreviewBox.innerHTML = img;
                        if (sidebarLogoPreview) sidebarLogoPreview.innerHTML = img;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        if (stampInput && stampPreviewBox) {
            stampInput.addEventListener('change', function () {
                var file = stampInput.files && stampInput.files[0];
                if (file && file.type.match(/^image\//)) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        stampPreviewBox.innerHTML = '<img src="' + e.target.result + '" alt="">';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        if (loginBackgroundInput && loginBackgroundPreview) {
            loginBackgroundInput.addEventListener('change', function () {
                var file = loginBackgroundInput.files && loginBackgroundInput.files[0];
                if (file && file.type.match(/^image\//)) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        loginBackgroundPreview.style.backgroundImage = 'linear-gradient(135deg, rgba(15,23,42,0.7), rgba(99,102,241,0.5)), url("' + e.target.result + '")';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Live Search across all tabs
        var searchInput = document.getElementById('businessSettingsSearch');
        var clearBtn = document.getElementById('clearSearchBtn');
        var searchTargets = document.querySelectorAll('[data-search-target]');

        var summary = document.getElementById('settingsSearchSummary');
        function clearSearch() {
            if (searchInput) searchInput.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            searchTargets.forEach(function (el) { el.style.display = ''; });
            navItems.forEach(function (item) { item.classList.remove('lm-no-match'); item.removeAttribute('data-matches'); });
            if (summary) summary.hidden = true;
        }
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var query = this.value.trim().toLocaleLowerCase();
                if (!query) { clearSearch(); return; }
                if (clearBtn) clearBtn.style.display = 'block';
                var counts = {}, firstFoundTab = null, total = 0;
                searchTargets.forEach(function (el) {
                    var values = Array.from(el.querySelectorAll('input:not([type=password]):not([type=hidden]), select, textarea')).map(function (field) {
                        return field.tagName === 'SELECT' ? field.selectedOptions[0] && field.selectedOptions[0].textContent : field.value;
                    }).join(' ');
                    var match = (el.textContent + ' ' + values).toLocaleLowerCase().includes(query);
                    el.style.display = match ? '' : 'none';
                    var pane = el.closest('.lm-tab-pane');
                    if (match && pane) { counts[pane.id] = (counts[pane.id] || 0) + 1; total++; firstFoundTab = firstFoundTab || pane.id; }
                });
                navItems.forEach(function (item) {
                    var count = counts[item.dataset.tab] || 0;
                    item.classList.toggle('lm-no-match', !count);
                    item.setAttribute('data-matches', count);
                });
                if (summary) { summary.hidden = false; summary.textContent = total ? total + text(' matching settings. Choose a section to see its results.', ' ការកំណត់ត្រូវគ្នា។ ជ្រើសរើសផ្នែកដើម្បីមើល។') : text('No settings found. Try another search.', 'រកមិនឃើញការកំណត់។ សូមស្វែងរកម្តងទៀត។'); }
                if (firstFoundTab) switchTab(firstFoundTab);
            });
            if (clearBtn) clearBtn.addEventListener('click', function () { clearSearch(); searchInput.focus(); });
        }

        var form = document.getElementById('businessSettingsForm');
        var saveBtn = document.getElementById('saveSettingsBtn');
        var status = document.getElementById('saveStatusText');
        var resetBtn = document.getElementById('resetSettingsBtn');
        var dirty = false, submitting = false;
        function snapshot() {
            return JSON.stringify(Array.from(new FormData(form)).filter(function (pair) { return !['_token', 'active_tab'].includes(pair[0]); }).map(function (pair) {
                return [pair[0], pair[1] instanceof File ? (pair[1].name ? [pair[1].name, pair[1].size, pair[1].lastModified] : null) : pair[1]];
            }));
        }
        var initial = form ? snapshot() : '';
        if (status && root.dataset.canSave !== '1') status.textContent = text('View only', 'អាចមើលតែប៉ុណ្ណោះ');
        root.querySelectorAll('.fa').forEach(function (icon) { icon.setAttribute('aria-hidden', 'true'); });
        function updateDirty() {
            dirty = snapshot() !== initial;
            root.classList.toggle('has-unsaved-changes', dirty);
            if (status) status.textContent = dirty ? text('Unsaved changes', 'មានការផ្លាស់ប្តូរមិនទាន់រក្សាទុក') : text('Ready to save changes', 'រួចរាល់សម្រាប់ការរក្សាទុក');
            if (resetBtn) resetBtn.disabled = !dirty;
        }
        function fieldByKey(key) {
            var parts = key.split('.');
            var name = parts.shift() + parts.map(function (part) { return '[' + part + ']'; }).join('');
            return Array.from(form.elements).find(function (field) { return field.name === name && field.type !== 'hidden'; });
        }
        function revealField(field) {
            if (!field) return;
            clearSearch();
            var pane = field.closest('.lm-tab-pane');
            if (pane) switchTab(pane.id);
            field.setAttribute('aria-invalid', 'true');
        }
        if (form) {
            form.addEventListener('input', function (event) { event.target.removeAttribute('aria-invalid'); updateDirty(); });
            form.addEventListener('change', updateDirty);
            form.addEventListener('invalid', function (event) { revealField(event.target); }, true);
            form.addEventListener('submit', function (event) {
                if (submitting || root.dataset.canSave !== '1') { event.preventDefault(); return; }
                submitting = true;
                if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = text('Saving…', 'កំពុងរក្សាទុក…'); }
            });
            document.querySelectorAll('[data-error-field]').forEach(function (button, index) {
                var field = fieldByKey(button.dataset.errorField);
                if (field) {
                    field.setAttribute('aria-invalid', 'true');
                    if (index === 0) revealField(field);
                    button.addEventListener('click', function () { revealField(field); field.focus(); });
                }
            });
            if (resetBtn) resetBtn.addEventListener('click', function () {
                if (dirty && window.confirm(text('Discard your unsaved changes?', 'បោះបង់ការផ្លាស់ប្តូរមិនទាន់រក្សាទុក?'))) { dirty = false; window.location.reload(); }
            });
            window.addEventListener('beforeunload', function (event) {
                if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; }
            });
            window.addEventListener('pageshow', function (event) { if (event.persisted) { submitting = false; if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = text('Save All Settings', 'រក្សាទុកការកំណត់ទាំងអស់'); } } });
            if (resetBtn) resetBtn.disabled = true;
        }
        // Switches get accessible names from their surrounding cards.
        document.querySelectorAll('.lm-switch input[type=checkbox]').forEach(function (input) {
            var card = input.closest('.lm-toggle-card, .lm-method-card');
            var title = card && card.querySelector('.lm-toggle-title, .lm-method-header-title');
            if (title && !input.getAttribute('aria-label')) input.setAttribute('aria-label', title.textContent.trim());
        });

        var token = document.getElementById('telegramBotTokenInput');
        var reveal = document.getElementById('toggleTelegramToken');
        var testBot = document.getElementById('testTelegramBot');
        var testResult = document.getElementById('telegramTestResult');
        if (token && reveal) reveal.addEventListener('click', function () {
            var visible = token.type === 'password'; token.type = visible ? 'text' : 'password';
            reveal.setAttribute('aria-pressed', String(visible)); reveal.textContent = visible ? text('Hide token', 'លាក់ Token') : text('Show token', 'បង្ហាញ Token');
        });
        if (token && testBot) testBot.addEventListener('click', async function () {
            testBot.disabled = true;
            testResult.textContent = text('Checking bot credentials…', 'កំពុងពិនិត្យ Bot…');
            try {
                var result = await fetch(testBot.dataset.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value }, body: JSON.stringify({ telegram_bot_token: token.value }) });
                var data = await result.json();
                testResult.textContent = result.ok ? data.message : data.errors ? text('Enter a valid Telegram bot token.', 'សូមបញ្ចូល Telegram Bot Token ត្រឹមត្រូវ។') : data.message || text('Unable to check connection.', 'មិនអាចពិនិត្យការតភ្ជាប់បាន។');
            } catch (error) { testResult.textContent = text('Connection unavailable. Please try again.', 'មិនអាចភ្ជាប់បាន។ សូមព្យាយាមម្តងទៀត។'); }
            finally { testBot.disabled = false; }
        });

        // CMS Live Headline Preview in Tab
        var cmsHInput = document.getElementById('homeHeadlineInput');
        var cmsSInput = document.getElementById('homeSubtitleInput');
        var cmsBInput = document.getElementById('homeBodyInput');
        var cmsPH = document.getElementById('cmsPreviewHeadline');
        var cmsPS = document.getElementById('cmsPreviewSubtitle');
        var cmsPB = document.getElementById('cmsPreviewBody');

        function syncCmsPreview() {
            if (cmsPH && cmsHInput) cmsPH.textContent = cmsHInput.value || '';
            if (cmsPS && cmsSInput) cmsPS.textContent = cmsSInput.value || '';
            if (cmsPB && cmsBInput) cmsPB.textContent = cmsBInput.value || '';
        }
        [cmsHInput, cmsSInput, cmsBInput].forEach(function(i) {
            if (i) i.addEventListener('input', syncCmsPreview);
        });

        var homeHeroInput = document.getElementById('homeHeroInput');
        var cmsHeroThumbnail = document.getElementById('cmsHeroThumbnail');
        var cmsPreviewHero = document.getElementById('cmsPreviewHero');
        if (homeHeroInput) {
            homeHeroInput.addEventListener('change', function () {
                var file = homeHeroInput.files && homeHeroInput.files[0];
                if (file && file.type.match(/^image\//)) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        if (cmsHeroThumbnail) cmsHeroThumbnail.src = e.target.result;
                        if (cmsPreviewHero) cmsPreviewHero.style.setProperty('--lm-hero-preview', 'url("' + e.target.result + '")');
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Social Media & Follow Us Live Preview and Verification
        var socialTitleInput = document.getElementById('socialTitleInput');
        var socialShowToggle = document.getElementById('socialShowToggle');
        var socialPreviewTitle = document.getElementById('socialPreviewTitle');
        var socialPreviewBody = document.getElementById('socialPreviewBody');
        var socialPreviewEmptyNotice = document.getElementById('socialPreviewEmptyNotice');

        var socialPlatformKeys = [
            'footer_facebook', 'footer_telegram', 'footer_tiktok', 'footer_instagram',
            'footer_youtube', 'footer_whatsapp', 'footer_linkedin', 'footer_twitter', 'footer_website'
        ];

        function syncSocialPreview() {
            if (socialPreviewTitle && socialTitleInput) {
                socialPreviewTitle.textContent = (socialTitleInput.value || '').trim() || 'FOLLOW US';
            }
            if (socialPreviewBody && socialShowToggle) {
                socialPreviewBody.style.opacity = socialShowToggle.checked ? '1' : '0.4';
            }

            var activeCount = 0;
            socialPlatformKeys.forEach(function(key) {
                var input = document.getElementById('cms_' + key);
                var iconEl = document.getElementById('preview_icon_' + key);
                var badgeEl = document.getElementById('badge_' + key);
                var testBtn = document.getElementById('test_btn_' + key);
                if (!input) return;

                var val = (input.value || '').trim();
                var fullUrl = '';
                try {
                    var parsed = new URL(/^[a-z][a-z0-9+.-]*:/i.test(val) ? val : 'https://' + val);
                    if (val && ['http:', 'https:'].includes(parsed.protocol) && parsed.hostname.includes('.')) fullUrl = parsed.href;
                } catch (error) {}
                var hasVal = !!fullUrl;
                if (hasVal) activeCount++;

                if (iconEl) {
                    iconEl.style.display = hasVal ? 'inline-grid' : 'none';
                    if (hasVal) {
                        iconEl.setAttribute('href', fullUrl);
                    }
                }

                if (badgeEl) {
                    if (hasVal) {
                        badgeEl.className = 'lm-social-badge active';
                        badgeEl.textContent = text('Configured', 'បានកំណត់');
                    } else {
                        badgeEl.className = 'lm-social-badge inactive';
                        badgeEl.textContent = text('Not Set', 'មិនទាន់កំណត់');
                    }
                }

                if (testBtn) {
                    if (hasVal) {
                        testBtn.href = fullUrl;
                        testBtn.classList.remove('disabled');
                        testBtn.removeAttribute('aria-disabled');
                    } else {
                        testBtn.removeAttribute('href');
                        testBtn.setAttribute('aria-disabled', 'true');
                        testBtn.classList.add('disabled');
                    }
                }
            });

            if (socialPreviewEmptyNotice) {
                socialPreviewEmptyNotice.style.display = activeCount === 0 ? 'block' : 'none';
            }
        }

        if (socialTitleInput) socialTitleInput.addEventListener('input', syncSocialPreview);
        if (socialShowToggle) socialShowToggle.addEventListener('change', syncSocialPreview);

        socialPlatformKeys.forEach(function(key) {
            var input = document.getElementById('cms_' + key);
            if (input) input.addEventListener('input', syncSocialPreview);
        });

        syncSocialPreview();

        syncPreview();
    })();
