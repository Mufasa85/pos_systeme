/**
 * paper-type.js
 * Override _printReceiptContent pour appliquer le format d'impression configuré
 * dans la table `settings` (clé: paper_type).
 * Formats supportés : 80mm, 57mm, A4, A5, Letter, Legal
 * Pour A4/A5/Letter/Legal : mise en page facture classique (en-tête horizontal)
 *
 * Expose :
 *   - window._printReceiptContent(content)              : utilise le format configuré
 *   - window._printReceiptContentWithFormat(content, p) : imprime avec un format donné
 *   - window.getCurrentPaperType(cb)                    : retourne le format actuel
 */
(function () {
    'use strict';

    // Cache du format d'impression (chargé depuis /api/settings/paper-type)
    let currentPaperType = '80mm';

    // Formats considérés comme "large" (facture classique horizontale)
    const LARGE_FORMATS = ['A4', 'A5', 'Letter', 'Legal'];

    function isLargeFormat(pt) {
        return LARGE_FORMATS.indexOf(pt) !== -1;
    }

    // Inject print styles into the main document head to support standard window.print()
    function injectDocumentPrintStyles(paperType) {
        var existingStyle = document.getElementById('dynamic-document-print-styles');
        if (existingStyle) existingStyle.remove();

        var pt = paperType || currentPaperType || '80mm';
        var sizeRule = '';
        var maxWidth = '76mm';
        var bodyFont = "'Courier New', Courier, monospace";
        var bodyFontSize = '14px';

        switch (pt) {
            case '57mm': sizeRule = '57mm auto'; maxWidth = '53mm'; bodyFontSize = '11px'; break;
            case '80mm': sizeRule = '80mm auto'; maxWidth = '76mm'; bodyFontSize = '14px'; break;
            case 'A4': sizeRule = 'A4 portrait'; maxWidth = '190mm'; bodyFontSize = '12px'; bodyFont = "'Helvetica Neue', Arial, sans-serif"; break;
            case 'A5': sizeRule = 'A5 portrait'; maxWidth = '128mm'; bodyFontSize = '11px'; bodyFont = "'Helvetica Neue', Arial, sans-serif"; break;
            case 'Letter': sizeRule = 'Letter portrait'; maxWidth = '190mm'; bodyFontSize = '12px'; bodyFont = "'Helvetica Neue', Arial, sans-serif"; break;
            case 'Legal': sizeRule = 'Legal portrait'; maxWidth = '190mm'; bodyFontSize = '12px'; bodyFont = "'Helvetica Neue', Arial, sans-serif"; break;
            default: sizeRule = '80mm auto'; maxWidth = '76mm'; bodyFontSize = '14px';
        }

        var css;
        if (isLargeFormat(pt)) {
            css = '@media print {\n' +
                '  body * { visibility: hidden !important; }\n' +
                '  .invoice-classic, .invoice-classic * { visibility: visible !important; }\n' +
                '  .invoice-classic {\n' +
                '    position: absolute !important; left: 0 !important; top: 0 !important;\n' +
                '    width: 100% !important; padding: 0 !important; margin: 0 auto !important;\n' +
                '    font-family: ' + bodyFont + ' !important;\n' +
                '    font-size: ' + bodyFontSize + ' !important;\n' +
                '  }\n' +
                '  .receipt { display: none !important; }\n' +
                '  .no-print { display: none !important; }\n' +
                '  @page { margin: 10mm; size: ' + sizeRule + '; }\n' +
                '}';
        } else {
            css = '@media print {\n' +
                '  body * { visibility: hidden !important; }\n' +
                '  .receipt, .receipt * { visibility: visible !important; }\n' +
                '  .receipt {\n' +
                '    position: absolute !important; left: 0 !important; top: 0 !important;\n' +
                '    width: 100% !important; max-width: ' + maxWidth + ' !important;\n' +
                '    padding: 0 !important; margin: 0 auto !important;\n' +
                '    font-family: ' + bodyFont + ' !important;\n' +
                '    font-size: ' + bodyFontSize + ' !important;\n' +
                '  }\n' +
                '  .page-facture-ticket { padding: 0 !important; min-height: auto !important; }\n' +
                '  .ticket-wrapper { box-shadow: none !important; max-width: ' + maxWidth + ' !important; border-radius: 0 !important; }\n' +
                '  .no-print { display: none !important; }\n' +
                '  @page { margin: 5mm; size: ' + sizeRule + '; }\n' +
                '}';
        }

        var style = document.createElement('style');
        style.id = 'dynamic-document-print-styles';
        style.type = 'text/css';
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
    }

    // Charger le format depuis l'API
    function loadPaperType(cb) {
        try {
            fetch(APP_URL + '/api/settings/paper-type', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.paper_type) currentPaperType = data.paper_type;
                    injectDocumentPrintStyles(currentPaperType);
                    if (typeof cb === 'function') cb(currentPaperType);
                })
                .catch(function () {
                    injectDocumentPrintStyles(currentPaperType);
                    if (typeof cb === 'function') cb(currentPaperType);
                });
        } catch (e) {
            injectDocumentPrintStyles(currentPaperType);
            if (typeof cb === 'function') cb(currentPaperType);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TRANSFORMATION : reçu ticket ➜ facture classique (layout A4/A5)
    // ──────────────────────────────────────────────────────────────────────────

    function numberToFrenchWords(n) {
        let sign = 1
        n = Math.floor(Number(n) || 0);
        if (n === 0) return 'zéro';
        if (n < 0) {
            sign = -1
            n = -1 * n
        }
        var units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
        var teens = ['dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        var tens = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
        var out = [];
        if (n >= 1000000) {
            var m = Math.floor(n / 1000000); n %= 1000000;
            out.push((m === 1 ? 'un million' : numberToFrenchWords(m) + ' millions'));
        }
        if (n >= 1000) {
            var t = Math.floor(n / 1000); n %= 1000;
            out.push((t === 1 ? 'mille' : numberToFrenchWords(t) + ' mille'));
        }
        if (n >= 100) {
            var h = Math.floor(n / 100); n %= 100;
            out.push((h === 1 ? 'cent' : units[h] + (n === 0 ? ' cents' : ' cent')));
        }
        if (n > 0) {
            if (n < 10) out.push(units[n]);
            else if (n < 20) out.push(teens[n - 10]);
            else {
                var td = Math.floor(n / 10), ud = n % 10;
                if (td === 7) {
                    out.push(ud === 1 ? 'soixante et onze' : 'soixante-' + teens[ud]);
                } else if (td === 9) {
                    out.push('quatre-vingt-' + teens[ud]);
                } else {
                    if (ud === 1 && td !== 8) out.push(tens[td] + ' et un');
                    else if (ud > 0) out.push(tens[td] + '-' + units[ud]);
                    else out.push(tens[td] + (td === 8 ? 's' : ''));
                }
            }
        }
        return out.join(' ').trim();
    }

    function extractGrandTotalNumber(totalsEl) {
        if (!totalsEl) return 0;
        // 1) Priorité à la ligne portant la classe .grand-total
        var grandRow = totalsEl.querySelector('.receipt-total-row.grand-total');
        if (grandRow) {
            var spans = grandRow.querySelectorAll('span');
            if (spans.length >= 2) {
                var raw = (spans[1].textContent || '').replace(/[^\d.,-]/g, '').replace(/\s/g, '').replace(',', '.');
                var v = parseFloat(raw);
                if (!isNaN(v)) return v;
            }
        }
        // 2) Sinon, parcourir toutes les lignes en gardant la plus grande valeur
        var rows = totalsEl.querySelectorAll('.receipt-total-row');
        var maxVal = 0;
        for (var i = 0; i < rows.length; i++) {
            var spans2 = rows[i].querySelectorAll('span');
            if (spans2.length >= 2) {
                var raw2 = (spans2[1].textContent || '').replace(/[^\d.,-]/g, '').replace(/\s/g, '').replace(',', '.');
                var v2 = parseFloat(raw2);
                if (!isNaN(v2) && v2 > maxVal) maxVal = v2;
            }
        }
        return maxVal;
    }

    /**
     * Transforme le HTML d'un .receipt (ticket vertical) en HTML d'une facture
     * classique A4/A5/Letter/Legal avec un layout horizontal.
     */
    function transformReceiptToInvoice(receiptHtml, paperType) {
        var tmp = document.createElement('div');
        tmp.innerHTML = receiptHtml;

        var receipt = tmp.querySelector('.receipt');
        if (!receipt) {
            return receiptHtml;
        }

        // Données magasin
        var storeNameEl = receipt.querySelector('.store-name');
        var storeName = storeNameEl ? storeNameEl.textContent.trim() : '';
        var storeInfoEl = receipt.querySelector('.store-info');
        var storeInfoHtml = storeInfoEl ? storeInfoEl.innerHTML : '';
        var storeInfoProcessedHtml = storeInfoHtml;
        if (storeInfoEl) {
            var tmpStoreInfo = document.createElement('div');
            tmpStoreInfo.innerHTML = storeInfoHtml;
            var rows = tmpStoreInfo.querySelectorAll('div');
            if (rows.length > 0) {
                var processedRows = '';
                rows.forEach(function (row) {
                    var text = (row.textContent || '').trim();
                    if (!text) return;
                    var parts = text.split(/:\s*/);
                    if (parts.length >= 2) {
                        var label = parts.shift().trim();
                        var value = parts.join(':').trim();

                        if (label.toLowerCase() === 'point de vente') {
                            // Ligne "Point de vente" : toute la ligne en gras
                            processedRows += '<div class="store-info-row store-info-row-bold"><span class="store-info-label">' + label + ' :</span>' +
                                '<span class="store-info-value"><strong>' + value + '</strong></span></div>';
                        } else {
                            processedRows += '<div class="store-info-row"><span class="store-info-label">' + label + '</span>' +
                                '<span class="store-info-value">' + value + '</span></div>';
                        }

                    } else {
                        processedRows += '<div class="store-info-row store-info-single">' + text + '</div>';
                    }
                });
                if (processedRows) {
                    storeInfoProcessedHtml = processedRows;
                }
            }
        }

        var infoSectionEl = null;
        var headerEl = receipt.querySelector('.receipt-header');
        if (headerEl) {
            var headerChildren = headerEl.children;
            for (var i = headerChildren.length - 1; i >= 0; i--) {
                var child = headerChildren[i];
                var styleAttr = (child.getAttribute && child.getAttribute('style')) || (child.style ? child.style.cssText : '') || '';
                if (styleAttr.indexOf('dashed') !== -1) {
                    infoSectionEl = child;
                    break;
                }
            }
        }
        var clientInfoHtml = infoSectionEl ? infoSectionEl.innerHTML : '';

        var barcodeEl = receipt.querySelector('.barcode');
        var barcode = barcodeEl ? barcodeEl.textContent.trim() : '';
        if (!barcode) {
            var thankYouEls = receipt.querySelectorAll('.thank-you');
            for (var t = 0; t < thankYouEls.length; t++) {
                var tyText = (thankYouEls[t].textContent || '').trim();
                var mNum = tyText.match(/FACTURE\s*n[°ºo]?\s*:?\s*([A-Za-z0-9\-_/]+)/i);
                if (mNum) { barcode = mNum[1].trim(); break; }
            }
        }
        var invoiceNum = barcode;
        var invoiceDate = '';

        var itemsEl = receipt.querySelector('.receipt-items');
        var itemsHtml = itemsEl ? itemsEl.innerHTML : '';

        var totalsEl = receipt.querySelector('.receipt-totals');
        var totalsHtml = totalsEl ? totalsEl.innerHTML : '';
        var grandTotalNum = extractGrandTotalNumber(totalsEl);

        var commentHtml = '';
        if (totalsEl) {
            var childs = totalsEl.children;
            for (var c = 0; c < childs.length; c++) {
                var ch = childs[c];
                if (ch.classList && ch.classList.contains('receipt-total-row')) continue;
                var txt = (ch.textContent || '').toLowerCase();
                if (txt.indexOf('commentaire') !== -1 || txt.indexOf('remarque') !== -1) {
                    commentHtml = ch.outerHTML;
                    break;
                }
            }
        }

        var metaEl = receipt.querySelector('.receipt-meta');
        var invoiceTypeLabel = '';
        var metaComment = '';
        var metaReference = '';
        if (metaEl) {
            // Le bloc .receipt-meta contient jusqu'à 3 <div> enfants :
            //   1er : type de facture (en-tête)
            //   2eme : commentaire sur la facture
            //   3eme : référence de la facture
            var metaDivs = [];
            for (var mi = 0; mi < metaEl.children.length; mi++) {
                if (metaEl.children[mi] && metaEl.children[mi].tagName === 'DIV') {
                    metaDivs.push(metaEl.children[mi]);
                }
            }
            if (metaDivs.length >= 1) invoiceTypeLabel = metaDivs[0].textContent.trim();
            if (metaDivs.length >= 2) metaComment = metaDivs[1].textContent.trim();
            if (metaDivs.length >= 3) metaReference = metaDivs[2].textContent.trim();

            // Fallback : si pas de <div> directes, tenter les <span>
            if (!invoiceTypeLabel) {
                var metaSpans = metaEl.querySelectorAll('span');
                if (metaSpans.length >= 1) invoiceTypeLabel = metaSpans[0].textContent.trim();
                if (metaSpans.length >= 2) metaComment = metaSpans[1].textContent.trim();
                if (metaSpans.length >= 3) metaReference = metaSpans[2].textContent.trim();
            }

            if (!invoiceTypeLabel) invoiceTypeLabel = metaEl.textContent.trim();
        }

        var dgiEl = null;
        var allDivs = receipt.querySelectorAll('div');
        for (var j = 0; j < allDivs.length; j++) {
            var d = allDivs[j];
            var rawStyle = d.getAttribute('style') || '';
            var cs = (typeof window !== 'undefined' && window.getComputedStyle) ? window.getComputedStyle(d) : null;
            var bg = rawStyle + ' | ' + (d.style.background || '') + ' | ' + (d.style.backgroundColor || '') +
                (cs ? ' | ' + cs.background + ' | ' + cs.backgroundColor : '');
            if (bg.indexOf('e8f5e9') !== -1 || bg.indexOf('4caf50') !== -1 ||
                bg.indexOf('232, 245, 233') !== -1 || bg.indexOf('76, 175, 80') !== -1) {
                dgiEl = d;
                break;
            }
        }
        var dgiHtml = dgiEl ? dgiEl.outerHTML : '';

        var dgiFields = { codeDEF: '', nim: '', counters: '', date: '', isf: '' };
        if (dgiEl) {
            var dgiText = dgiEl.textContent || '';
            var m;
            m = dgiText.match(/CODE\s*DEF\/DGI\s*[:\-]?\s*([^\n<]+?)(?=\s*(?:DEF|ISF|$))/i);
            if (m) dgiFields.codeDEF = m[1].trim();
            m = dgiText.match(/DEF\s*NID\s*[:\-]?\s*([^\n<]+?)(?=\s*(?:DEF|ISF|$))/i);
            if (m) dgiFields.nim = m[1].trim();
            m = dgiText.match(/DEF\s*Compteurs\s*[:\-]?\s*([^\n<]+?)(?=\s*(?:DEF|ISF|$))/i);
            if (m) dgiFields.counters = m[1].trim();
            m = dgiText.match(/DEF\s*Heure\s*[:\-]?\s*([^\n<]+?)(?=\s*ISF|$)/i);
            if (m) dgiFields.date = m[1].trim();
            m = dgiText.match(/ISF\s*[:\-]?\s*([^\n<]+?)$/i);
            if (m) dgiFields.isf = m[1].trim();
        }

        if (!dgiFields.isf && totalsEl) {
            var isfInTotals = totalsEl.textContent.match(/ISF\s*[:\-]?\s*([^\n<]+?)(?=\s*(?:[A-Z][a-z]|$))/i);
            if (isfInTotals) dgiFields.isf = isfInTotals[1].trim();
        }

        // 1) Depuis dgiFields.date (DEF Heure)
        if (!invoiceDate) invoiceDate = dgiFields.date;
        // 2) Depuis le footer (Date : ...)
        if (!invoiceDate) {
            var footerEl = receipt.querySelector('.receipt-footer');
            if (footerEl) {
                var dateText = footerEl.textContent.match(/Date\s*[:\-]?\s*([0-9\/\-\s:]+)/i);
                if (dateText) invoiceDate = dateText[1].trim();
            }
        }
        // 3) Fallback : date/heure actuelle
        if (!invoiceDate) {
            var now = new Date();
            var pad = function (n) { return (n < 10 ? '0' + n : '' + n); };
            invoiceDate = pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear() +
                ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        }

        var qrEl = receipt.querySelector('.qrcode-container');
        var qrHtml = qrEl ? qrEl.outerHTML : '';

        var clientRows = '';
        if (clientInfoHtml) {
            var tmpClient = document.createElement('div');
            tmpClient.innerHTML = clientInfoHtml;
            var pairs = tmpClient.querySelectorAll('div[style*="space-between"], div[style*="flex"]');
            if (pairs.length === 0) {
                clientRows = '<tr><td colspan="2" style="font-size:11px;padding:3px 0;">' + clientInfoHtml + '</td></tr>';
            } else {
                pairs.forEach(function (row) {
                    var spans = row.querySelectorAll('span');
                    if (spans.length >= 2) {
                        var label = spans[0].textContent.trim();
                        var value = spans[1].textContent.trim();
                        clientRows += '<tr><td style="color:#666;padding:3px 8px 3px 0;font-size:11px;white-space:nowrap;">' + label + '</td>' +
                            '<td style="font-weight:600;padding:3px 0;font-size:11px;">' + value + '</td></tr>';
                    }
                });
            }
        }

        var itemTableHtml = '';
        var tmpItems = document.createElement('div');
        tmpItems.innerHTML = itemsHtml;
        var existingTable = tmpItems.querySelector('table.receipt-table, table');
        if (existingTable) {
            itemTableHtml = existingTable.outerHTML;
        } else {
            var rows = '';
            var itemEls = tmpItems.querySelectorAll('.receipt-item');
            itemEls.forEach(function (el) {
                var nameEl2 = el.querySelector('.item-name');
                var qtyEl = el.querySelector('.item-qty');
                var priceEl = el.querySelector('.item-price, .item-total');
                var name = nameEl2 ? nameEl2.textContent.trim() : el.textContent.trim();
                var qtyPrice = qtyEl ? qtyEl.textContent.trim() : '';
                var totalPrice = priceEl ? priceEl.textContent.trim() : '';
                rows += '<tr>' +
                    '<td style="padding:6px 8px;border-bottom:1px solid #eee;">' + name + '</td>' +
                    '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap;">' + qtyPrice + '</td>' +
                    '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right;font-weight:600;">' + totalPrice + '</td>' +
                    '</tr>';
            });
            itemTableHtml = '<table style="width:100%;border-collapse:collapse;">' +
                '<thead><tr style="background:#f5f5f5;">' +
                '<th style="padding:8px;text-align:left;border-bottom:2px solid #333;">Article</th>' +
                '<th style="padding:8px;text-align:left;border-bottom:2px solid #333;">Qté x Prix unitaire</th>' +
                '<th style="padding:8px;text-align:right;border-bottom:2px solid #333;">Total HT</th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table>';
        }

        var totTableHtml = '';
        var paymentRowsHtml = '';
        var tmpTotals = document.createElement('div');
        tmpTotals.innerHTML = totalsHtml;
        var totalRows = tmpTotals.querySelectorAll('.receipt-total-row');
        if (totalRows.length > 0) {
            var tRows = '';
            totalRows.forEach(function (row) {
                var spans = row.querySelectorAll('span');
                if (spans.length >= 2) {
                    var label = (spans[0].textContent || '').trim();
                    var normalized = label.toLowerCase();
                    var isQtyLine = normalized.indexOf('qté') !== -1 || normalized.indexOf('qte') !== -1 || normalized.indexOf("nombre d'article") !== -1;
                    var isPaymentInfo = normalized.indexOf('taux du jour') !== -1 ||
                        normalized.indexOf('equivalent en usd') !== -1 ||
                        normalized.indexOf('paiment') !== -1 ||
                        normalized.indexOf('paiement') !== -1 ||
                        isQtyLine;
                    if (isPaymentInfo) {
                        var displayLabel = isQtyLine ? "Nombre d'articles" : label;
                        paymentRowsHtml += '<div class="inv-payment-row"><span>' + displayLabel + '</span>' +
                            '<span>' + spans[1].textContent.trim() + '</span></div>';
                        return;
                    }

                    
                    var isGrand = row.classList.contains('grand-total');
                    var style = isGrand
                        ? 'font-weight:700;font-size:14px;border-top:2px solid #333;'
                        : 'font-size:12px;';
                    tRows += '<tr style="' + style + '">' +
                        '<td style="padding:5px 8px;text-align:right;color:#555;">' + label + '</td>' +
                        '<td style="padding:5px 8px;text-align:right;font-weight:' + (isGrand ? '700' : '500') + ';">' + spans[1].textContent.trim() + '</td>' +
                        '</tr>';

                }


            });
            totTableHtml = '<table style="width:100%;border-collapse:collapse;min-width:300px;">' +
                '<tbody>' + tRows + '</tbody></table>';
        } else {
            totTableHtml = totalsHtml;
        }

        console.log(totTableHtml)

        if (paymentRowsHtml) {
            paymentRowsHtml = '<div class="inv-payment-info">' + paymentRowsHtml + '</div>';
        }

        // Utiliser la valeur extraite du reçu (fonctionne pour /caisse ET /historique)
        var totalEnLettre = numberToFrenchWords(grandTotalNum);
        var totalEnLettreHtml =
            '<div class="inv-amount-spelled">' +
            '  Arrêté la présente facture à la somme de <strong>' +
            '  <span style="text-transform:capitalize;">' + totalEnLettre + '</span> francs congolais' +
            (grandTotalNum > 0 ? ' (' + grandTotalNum.toFixed(2).replace('.', ',') + ' Fc)' : '') +
            '  </strong> toutes taxes comprises.' +
            '</div>';

        var sizeMap = { A4: 'A4 portrait', A5: 'A5 portrait', Letter: 'Letter portrait', Legal: 'Legal portrait' };
        var sizeRule = sizeMap[paperType] || 'A4 portrait';
        var fontSize = (paperType === 'A5') ? '11px' : '12px';

        var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
            '<style>\n' +
            '@page { margin: 12mm 15mm; size: ' + sizeRule + '; }\n' +
            '* { box-sizing: border-box; margin: 0; padding: 0; }\n' +
            'html, body { width: 100%; font-family: \'Helvetica Neue\', Arial, sans-serif; font-size: ' + fontSize + '; color: #000; background: #fff; line-height: 1.5; }\n' +
            '.invoice-wrap { width: 100%; max-width: 100%; }\n' +
            '.inv-top-row { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid #000; padding-bottom: 14px; margin-bottom: 16px; align-items: stretch; }\n' +
            '.inv-top-row .store-block, .inv-top-row .client-block { flex: 1 1 48%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; background: #fff; }\n' +
            '.inv-top-row .client-block { background: #fafafa; }\n' +
            '.inv-top-row .store-block .store-name { font-size: 18px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #111; margin-bottom: 6px; }\n' +
            '.inv-top-row .store-block .store-info { display: flex; flex-direction: column; gap: 4px; font-size: 11px; color: #444; line-height: 1.6; }\n' +
            '.inv-top-row .store-block .store-info > div { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }\n' +
            '.inv-top-row .store-block .store-info .store-info-row-bold { font-weight: 700; color: #111; }\n' +
            '.inv-top-row .store-block .store-info .store-info-row-bold .store-info-label { font-weight: 700; }\n' +
            '.inv-top-row .store-block .store-info .store-info-row-bold .store-info-value { font-weight: 700; }\n' +
            '.inv-top-row .client-block h4 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; }\n' +
            '.inv-top-row .client-block table { border-collapse: collapse; width: 100%; }\n' +
            '.inv-top-row .client-block td { vertical-align: top; }\n' +
            '.inv-top-row .client-block .invoice-type-big { font-size: 18px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #111; margin-bottom: 4px; }\n' +
            '.inv-top-row .client-block .invoice-num-line { font-size: 12px; color: #333; margin-bottom: 2px; }\n' +
            '.inv-top-row .client-block .invoice-date-line { font-size: 11px; color: #666; margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid #ddd; }\n' +
            '.inv-top-row .client-block .invoice-meta-line { font-size: 11px; color: #333; margin-bottom: 3px; line-height: 1.5; }\n' +
            '.inv-top-row .client-block .invoice-meta-line .meta-label { color: #666; font-weight: 600; margin-right: 4px; }\n' +
            '.inv-top-row .client-block .invoice-meta-comment { background: #fffbe6; border-left: 3px solid #f0ad4e; padding: 5px 8px; border-radius: 3px; font-size: 11px; color: #333; margin-bottom: 4px; }\n' +
            '.inv-top-row .client-block .invoice-meta-reference { background: #eef6fb; border-left: 3px solid #5bc0de; padding: 5px 8px; border-radius: 3px; font-size: 11px; color: #333; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #ddd; }\n' +
            '.inv-items { margin-bottom: 18px; }\n' +
            '.inv-items table { width: 100%; border-collapse: collapse; border: none; margin-bottom : 50px }\n' +
            '.inv-items table thead tr { background: transparent; color: inherit; }\n' +
            '.inv-items table thead th { padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; border: none; background: transparent; color: #111; }\n' +
            '.inv-items table thead th:last-child { text-align: right; }\n' +
            '.inv-items table thead th:nth-child(2) { text-align: center; }\n' +
            '.inv-items table tbody td { padding: 7px 10px; font-size: 12px; border: none; vertical-align: middle !important; }\n' +
            '.inv-items table tbody td:last-child { text-align: right; font-weight: 600; border: none; }\n' +
            '.inv-items table tbody td:nth-child(2) { text-align: center; }\n' +
            '.item-tax-badge { display: inline-block; font-size: 9px; background: #e8f5e9; color: #2e7d32; border: 1px solid #2e7d32; border-radius: 3px; padding: 1px 5px; margin-left: 5px; }\n' +
            '.item-prod-service { display: inline-block; font-size: 9px; background: #e3f2fd; color: #1565c0; border: 1px solid #1565c0; border-radius: 3px; padding: 1px 5px; margin-left: 5px; }\n' +
            '.inv-totals { position: relative; display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-top: -12px; margin-bottom: 16px; padding-top: 18px; }\n' +
            '.inv-totals::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: #333; }\n' +
            '.inv-totals .inv-payment-info { flex: 0 0 220px; display: flex; flex-direction: column; gap: 6px; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa; }\n' +
            '.inv-totals .inv-payment-row { display: flex; justify-content: space-between; gap: 8px; font-size: 11px; color: #444; }\n' +
            '.inv-totals table { width: 100%; flex: 1; border-collapse: collapse; min-width: 300px; max-width: 100%; }\n' +
            '.inv-totals td { padding: 5px 10px; font-size: 12px; white-space: nowrap; }\n' +
            '.inv-totals .grand-total td { font-size: 14px; font-weight: 700; border-top: none; padding-top: 8px; }\n' +
            '.inv-amount-spelled { border-radius: 4px; padding: 10px 14px; margin-bottom: 16px; background: #f5f5f5; font-size: 12px; line-height: 1.6; text-align : center}\n' +
            '.inv-security-row { display: flex; gap: 16px; align-items: stretch; border-top: 2px solid #000; padding-top: 14px; margin-top: 4px; margin-bottom: 14px; }\n' +
            '.inv-security-row .qr-block { flex: 0 0 180px; text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 6px; background: #fff; }\n' +
            '.inv-security-row .qr-block .qrcode-container { display: inline-block; }\n' +
            '.inv-security-row .qr-block .barcode { font-size: 13px; letter-spacing: 2px; font-weight: 700; margin-top: 8px; word-break: break-all; }\n' +
            '.inv-security-row .qr-block .qr-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-top: 6px; }\n' +
            '.qrcode-container svg, .qrcode-container img { max-width: 140px; height: auto; display: block; margin: 0 auto; }\n' +
            '.inv-security-row .dgi-block { flex: 1; padding: 10px 14px; border: 1px solid #4caf50; border-radius: 6px; background: #e8f5e9; font-size: 12px; color: #1b5e20; }\n' +
            '.inv-security-row .dgi-block .dgi-title { font-weight: 700; color: #2e7d32; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }\n' +
            '.inv-security-row .dgi-block table { border-collapse: collapse; width: 100%; }\n' +
            '.inv-security-row .dgi-block td { padding: 2px 6px; font-size: 11px; color: #1b5e20; vertical-align: top; }\n' +
            '.inv-comment { border: 1px dashed #999; border-radius: 4px; padding: 10px 14px; margin-bottom: 12px; background: #fffbe6; font-size: 11px; color: #333; }\n' +
            '.inv-comment .inv-comment-title { font-weight: 700; text-decoration: underline; margin-bottom: 4px; color: #555; }\n' +
            '.inv-footer-note { text-align: center; font-size: 9px; color: #777; font-style: italic; margin-top: 10px; }\n' +
            '</style></head><body>\n' +
            '<div class="invoice-classic invoice-wrap">\n' +
            '<div class="inv-top-row">\n' +
            '  <div class="store-block">\n' +
            '    <div class="store-name">' + storeName + '</div>\n' +
            '    <div class="store-info">' + storeInfoProcessedHtml + '</div>\n' +
            '  </div>\n' +
            '  <div class="client-block">\n' +
            '    <div class="invoice-type-big">' + (invoiceTypeLabel || 'Facture de Vente') + '</div>\n' +
            '    <div class="invoice-num-line">N° Facture : ' + (invoiceNum || '') + '</div>\n' +
            '    <div class="invoice-date-line">Date : ' + (invoiceDate || '') + '</div>\n' +
            (metaComment ? '    <div class="invoice-meta-comment"><span class="meta-label"> ' + invoiceTypeLabel + ' :</span> ' + metaComment + '</div>\n' : '') +
            (metaReference ? '    <div class="invoice-meta-reference"><span class="meta-label">Référence :</span> ' + metaReference + '</div>\n' : '') +
            '    <h4>Informations Client & Vendeur</h4>\n' +
            '    <table><tbody>' + clientRows + '</tbody></table>\n' +
            '  </div>\n' +
            '</div>\n' +
            '<div class="inv-items">\n' + itemTableHtml + '\n</div>\n' +
            '<div class="inv-totals">\n' + paymentRowsHtml + totTableHtml + '\n</div>\n' +
            totalEnLettreHtml + '\n' +
            '<div class="inv-security-row">\n' +
            '  <div class="qr-block">\n' +
            '    ' + qrHtml + '\n' +
            '    <div class="qr-label">Code QR</div>\n' +
            (barcode ? '    <div class="barcode">' + barcode + '</div>\n' : '') +
            '  </div>\n' +
            '  <div class="dgi-block">\n' +
            '    <div class="dgi-title">--- Éléments de sécurité de la facture normalisée ---</div>\n' +
            '    <table>\n' +
            (dgiFields.codeDEF ? '      <tr><td><strong>CODE DEF/DGI :</strong></td><td>' + dgiFields.codeDEF + '</td></tr>\n' : '') +
            (dgiFields.nim ? '      <tr><td><strong>DEF NID :</strong></td><td>' + dgiFields.nim + '</td></tr>\n' : '') +
            (dgiFields.counters ? '      <tr><td><strong>DEF Compteurs :</strong></td><td>' + dgiFields.counters + '</td></tr>\n' : '') +
            (ren.dateTime ? '      <tr><td><strong>DEF Heure :</strong></td><td>' + ren.dateTime + '</td></tr>\n' : '') +

            (!dgiFields.codeDEF && !dgiFields.nim && !dgiFields.counters && !dgiFields.date && !dgiFields.isf ? '      <tr><td colspan="2">Aucune information DGI disponible.</td></tr>\n' : '') +
            '    </table>\n' +
            '  </div>\n' +
            '</div>\n' +
            (commentHtml ? (
                '<div class="inv-comment">\n' +
                '  <div class="inv-comment-title">Commentaire DGI</div>\n' +
                '  ' + commentHtml + '\n' +
                '</div>\n'
            ) : '') +
            '<div class="inv-footer-note">--- Powered by Osat ---</div>\n' +
            '</div>\n' +
            '</body></html>';

        return html;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // buildPrintStyles – pour ticket 57mm / 80mm (utilisé dans l'iframe)
    // ──────────────────────────────────────────────────────────────────────────
    function buildPrintStyles(paperType) {
        var pt = paperType || currentPaperType || '80mm';
        var sizeRule = '';
        var maxWidth = '76mm';
        var bodyFont = "'Courier New', Courier, monospace";
        var bodyFontSize = '14px';
        var wrapperWidth = '80mm';

        switch (pt) {
            case '57mm': sizeRule = '57mm auto'; maxWidth = '53mm'; wrapperWidth = '57mm'; bodyFontSize = '11px'; break;
            case '80mm': sizeRule = '80mm auto'; maxWidth = '76mm'; wrapperWidth = '80mm'; bodyFontSize = '14px'; break;
            default: sizeRule = '80mm auto'; maxWidth = '76mm'; wrapperWidth = '80mm'; bodyFontSize = '14px';
        }

        return '\n<style>\n' +
            '@page { margin: 5mm; size: ' + sizeRule + '; }\n' +
            '* { box-sizing: border-box; margin: 0; padding: 0; }\n' +
            'html, body { width: 100%; }\n' +
            'body { font-family: ' + bodyFont + '; font-size: ' + bodyFontSize + '; line-height: 1.5; max-width: ' + maxWidth + '; margin: 0 auto; color: #000; background: #fff; }\n' +
            '.receipt { width: 100%; }\n' +
            '.receipt-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 12px; }\n' +
            '.receipt-header .store-name { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }\n' +
            '.receipt-header .store-info { font-size: 13px; line-height: 1.6; color: #222; }\n' +
            '.receipt-meta { display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; padding: 8px 0; margin-bottom: 10px; border-bottom: 2px solid #000; }\n' +
            '.receipt-items { margin-bottom: 10px; }\n' +
            '.receipt-item { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 5px; font-size: 13px; gap: 3px; }\n' +
            '.receipt-item .item-name { flex: 2; min-width: 0; white-space: normal; overflow-wrap: break-word; }\n' +
            '.receipt-item .item-qty { flex: 1; text-align: center; white-space: nowrap; }\n' +
            '.receipt-item .item-price { flex: 1; text-align: right; font-weight: 700; white-space: nowrap; }\n' +
            '.receipt-table { width: 100%; border-collapse: collapse; }\n' +
            '.receipt-table th { text-align: left; padding: 4px 0; border-bottom: 1px solid #000; font-size: 12px; }\n' +
            '.receipt-table td { padding: 3px 0; font-size: 12px; vertical-align: top; }\n' +
            '.receipt-table td:nth-child(2) { text-align: left; white-space: nowrap; }\n' +
            '.receipt-table td:last-child { text-align: right; font-weight: 600; }\n' +
            '.item-tax-badge { display: inline-block; font-size: 9px; border: 1px solid #999; border-radius: 2px; padding: 0 3px; margin-left: 3px; }\n' +
            '.receipt-totals { margin-bottom: 8px; }\n' +
            '.receipt-total-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 5px; }\n' +
            '.receipt-total-row.grand-total { font-size: 18px; font-weight: 700; border-top: 3px solid #000; border-bottom: 3px solid #000; padding: 8px 0; margin-top: 8px; }\n' +
            'div[style*="e8f5e9"], div[style*="4caf50"] { border-radius: 4px; padding: 8px 10px !important; margin: 10px 0 !important; font-size: 13px !important; }\n' +
            '.receipt-footer { text-align: center; margin-top: 12px; padding-top: 10px; border-top: 2px solid #000; font-size: 13px; }\n' +
            '.vendeur-info { margin-bottom: 8px; }\n' +
            '.qrcode-container { width: 100%; text-align: center; margin: 10px 0; overflow: visible; }\n' +
            '.qrcode-container > div { display: inline-block; overflow: visible; }\n' +
            '.qrcode-container svg, .qrcode-container img { display: block; margin: 0 auto; max-width: 250px; height: auto; overflow: visible; }\n' +
            '.barcode { font-size: 18px; letter-spacing: 3px; font-weight: 700; margin: 8px 0; text-align: center; }\n' +
            '.thank-you { font-style: italic; margin-top: 8px; font-size: 13px; }\n' +
            '</style>\n';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Moteur d'impression (utilise un paperType spécifique)
    // ──────────────────────────────────────────────────────────────────────────
    function _runPrint(content, paperType) {
        var oldFrame = document.getElementById('_print-frame');
        if (oldFrame) oldFrame.remove();

        var iframe = document.createElement('iframe');
        iframe.id = '_print-frame';
        iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:210mm;height:1px;border:none;overflow:hidden;';
        document.body.appendChild(iframe);

        var fullHtml;
        var useLarge = isLargeFormat(paperType);

        if (useLarge) {
            fullHtml = transformReceiptToInvoice(content, paperType);
        } else {
            var printStyles = buildPrintStyles(paperType);
            fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8">' + printStyles + '</head><body>' + content + '</body></html>';
        }

        var doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.open();
        doc.write(fullHtml);
        doc.close();

        iframe.onload = function () {
            setTimeout(function () {
                try {
                    var iDoc = iframe.contentDocument;
                    if (iDoc && iDoc.querySelectorAll) {
                        iDoc.querySelectorAll('.qrcode-container svg').forEach(function (svg) {
                            var origW = parseInt(svg.getAttribute('width') || 250, 10);
                            var origH = parseInt(svg.getAttribute('height') || 250, 10);
                            if (!svg.getAttribute('viewBox')) {
                                svg.setAttribute('viewBox', '0 0 ' + origW + ' ' + origH);
                            }
                            var qrSize = useLarge ? '120' : '220';
                            svg.setAttribute('width', qrSize);
                            svg.setAttribute('height', qrSize);
                            svg.style.width = qrSize + 'px';
                            svg.style.height = qrSize + 'px';
                            svg.style.display = 'block';
                            svg.style.margin = '0 auto';
                            svg.style.overflow = 'visible';
                        });
                    }
                    if (iframe.contentWindow) {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                    }
                } catch (e) {
                    console.error('Erreur impression iframe:', e);
                }
                setTimeout(function () { if (iframe.parentNode) iframe.remove(); }, 2000);
            }, 400);
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // API publique
    // ──────────────────────────────────────────────────────────────────────────

    // Impression avec le format configuré (par défaut 80mm)
    window._printReceiptContent = function (content) {
        loadPaperType(function (paperType) {
            currentPaperType = paperType;
            _runPrint(content, paperType);
        });
    };

    // Impression avec un format choisi à la volée (modal de sélection)
    window._printReceiptContentWithFormat = function (content, paperType) {
        var pt = paperType || currentPaperType || '80mm';
        _runPrint(content, pt);
    };

    // Récupérer le format actuel
    window.getCurrentPaperType = function (cb) {
        if (typeof cb === 'function') loadPaperType(cb);
        return currentPaperType;
    };

    // Liste des formats disponibles (utilisée par le modal de sélection)
    window.PAPER_FORMATS = [
        { key: '57mm', label: '57 mm', description: 'Ticket compact', icon: 'receipt' },
        { key: '80mm', label: '80 mm', description: 'Ticket standard', icon: 'receipt' },
        { key: 'A4', label: 'A4', description: 'Facture A4 (210×297 mm)', icon: 'description' },
        //{ key: 'A5', label: 'A5', description: 'Facture A5 (148×210 mm)', icon: 'description' },
        //{ key: 'Letter', label: 'Letter', description: 'Format US Letter', icon: 'description' },
        //{ key: 'Legal', label: 'Legal', description: 'Format US Legal', icon: 'description' }
    ];

    // Charger le format au démarrage (best-effort, non bloquant)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { loadPaperType(); });
    } else {
        loadPaperType();
    }
})();
