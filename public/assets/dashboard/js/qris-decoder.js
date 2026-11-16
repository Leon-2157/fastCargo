(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.FastCargoQris = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var textEncoder = typeof TextEncoder !== 'undefined' ? new TextEncoder() : null;
    var textDecoder = typeof TextDecoder !== 'undefined' ? new TextDecoder('utf-8') : null;

    function encodeUtf8(str) {
        if (textEncoder) {
            return textEncoder.encode(str);
        }
        var utf8 = unescape(encodeURIComponent(str));
        var arr = new Uint8Array(utf8.length);
        for (var i = 0; i < utf8.length; i++) {
            arr[i] = utf8.charCodeAt(i);
        }
        return arr;
    }

    function decodeUtf8(bytes) {
        if (textDecoder) {
            return textDecoder.decode(bytes);
        }
        var s = '';
        for (var i = 0; i < bytes.length; i++) {
            s += String.fromCharCode(bytes[i]);
        }
        try {
            return decodeURIComponent(escape(s));
        } catch (e) {
            return s;
        }
    }

    function cleanQrisString(raw) {
        return String(raw || '').replace(/[\r\n]+/g, '').trim();
    }

    function calculateCrc16Ccitt(payloadWithoutCrc) {
        var bytes = encodeUtf8(String(payloadWithoutCrc || ''));
        var crc = 0xFFFF;
        for (var i = 0; i < bytes.length; i++) {
            crc ^= (bytes[i] << 8);
            for (var j = 0; j < 8; j++) {
                if (crc & 0x8000) {
                    crc = ((crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    crc = (crc << 1) & 0xFFFF;
                }
            }
        }
        return crc.toString(16).toUpperCase().padStart(4, '0');
    }

    function parseEmvcoTlv(raw) {
        var bytes = encodeUtf8(String(raw || ''));
        var tags = {};
        var order = [];
        var offset = 0;

        while (offset + 4 <= bytes.length) {
            var tag = String.fromCharCode(bytes[offset], bytes[offset + 1]);
            var lenStr = String.fromCharCode(bytes[offset + 2], bytes[offset + 3]);

            if (!/^\d{2}$/.test(tag)) {
                return { tags: {}, order: [], validStructure: false, error: 'Malformed TLV tag at byte ' + offset };
            }

            if (!/^\d{2}$/.test(lenStr)) {
                return { tags: {}, order: [], validStructure: false, error: 'Malformed TLV length at byte ' + offset };
            }

            var len = parseInt(lenStr, 10);
            offset += 4;

            if (offset + len > bytes.length) {
                return { tags: {}, order: [], validStructure: false, error: 'Truncated TLV value for tag ' + tag };
            }

            var value = decodeUtf8(bytes.subarray(offset, offset + len));
            tags[tag] = value;
            order.push(tag);
            offset += len;
        }

        if (offset !== bytes.length) {
            return { tags: {}, order: [], validStructure: false, error: 'Trailing bytes in EMVCo TLV payload' };
        }

        return { tags: tags, order: order, validStructure: order.length > 0, error: null };
    }

    function resolveAcquirerLabel(domain) {
        var upper = String(domain || '').trim().toUpperCase();
        if (!upper) {
            return null;
        }
        if (upper.indexOf('GO-JEK') !== -1 || upper.indexOf('GOPAY') !== -1) {
            return 'GoPay / GoBiz';
        }
        if (upper.indexOf('SHOPEE') !== -1 || upper.indexOf('AIRPAY') !== -1) {
            return 'ShopeePay';
        }
        if (upper.indexOf('DANA') !== -1) {
            return 'DANA';
        }
        if (upper.indexOf('LINKAJA') !== -1) {
            return 'LinkAja';
        }
        if (upper.indexOf('OVO') !== -1) {
            return 'OVO';
        }
        if (upper.indexOf('BCA') !== -1) {
            return 'BCA QRIS';
        }
        if (upper.indexOf('BRI') !== -1) {
            return 'BRI QRIS';
        }
        if (upper.indexOf('MANDIRI') !== -1) {
            return 'Bank Mandiri';
        }
        if (upper.indexOf('BNI') !== -1) {
            return 'BNI QRIS';
        }
        if (upper.indexOf('NOBU') !== -1) {
            return 'Nobu Bank QRIS';
        }
        if (upper.indexOf('QRIS.WWW') !== -1) {
            return 'QRIS National';
        }
        return domain;
    }

    function parseEmvcoQris(rawInput) {
        var raw = cleanQrisString(rawInput);
        var result = {
            raw: raw,
            valid: false,
            crcValid: false,
            payloadFormat: null,
            poiMethod: null,
            poiLabel: null,
            merchantName: null,
            merchantCity: null,
            postalCode: null,
            countryCode: null,
            currencyCode: null,
            mcc: null,
            amount: null,
            acquirerDomain: null,
            acquirerLabel: null,
            merchantPan: null,
            merchantId: null,
            nmid: null,
            criteria: null,
            crcProvided: null,
            crcComputed: null,
            accounts: [],
            error: null
        };

        if (!raw) {
            result.error = 'QRIS string is empty.';
            return result;
        }

        if (raw.indexOf('000201') !== 0) {
            result.error = 'Must start with EMVCo QRIS header (000201).';
            return result;
        }

        var tlv = parseEmvcoTlv(raw);
        if (!tlv.validStructure) {
            result.error = tlv.error || 'Malformed EMVCo TLV format.';
            return result;
        }

        var tags = tlv.tags;
        result.payloadFormat = tags['00'] || null;
        result.poiMethod = tags['01'] || null;
        if (result.poiMethod === '11') {
            result.poiLabel = 'Static QRIS (11)';
        } else if (result.poiMethod === '12') {
            result.poiLabel = 'Dynamic QRIS (12)';
        }
        result.mcc = tags['52'] || null;
        result.currencyCode = tags['53'] || null;
        result.amount = tags['54'] || null;
        result.countryCode = tags['58'] || null;
        result.merchantName = tags['59'] ? (tags['59'].trim() || null) : null;
        result.merchantCity = tags['60'] ? (tags['60'].trim() || null) : null;
        result.postalCode = tags['61'] || null;
        result.crcProvided = tags['63'] ? tags['63'].toUpperCase() : null;

        var crcIdx = raw.lastIndexOf('6304');
        if (crcIdx !== -1 && raw.length === crcIdx + 8 && result.crcProvided) {
            result.crcComputed = calculateCrc16Ccitt(raw.slice(0, crcIdx + 4));
            result.crcValid = result.crcComputed === result.crcProvided;
        }

        for (var tagNum = 26; tagNum <= 51; tagNum++) {
            var tagKey = String(tagNum);
            if (!tags[tagKey]) {
                continue;
            }
            var subTlv = parseEmvcoTlv(tags[tagKey]);
            if (!subTlv.validStructure) {
                continue;
            }
            var sub = subTlv.tags;
            var account = {
                tag: tagKey,
                domain: sub['00'] || null,
                acquirer: resolveAcquirerLabel(sub['00'] || null),
                pan: sub['01'] || null,
                merchantId: sub['02'] || null,
                criteria: sub['03'] || null
            };
            result.accounts.push(account);

            if (!result.acquirerDomain && account.domain && tagNum < 51) {
                result.acquirerDomain = account.domain;
                result.acquirerLabel = account.acquirer;
            }
            if (!result.merchantPan && account.pan) {
                result.merchantPan = account.pan;
            }
            if (!result.merchantId && account.merchantId) {
                result.merchantId = account.merchantId;
            }
            if (!result.criteria && account.criteria) {
                result.criteria = account.criteria;
            }
            if (tagNum === 51 && account.merchantId) {
                result.nmid = account.merchantId;
            }
        }

        if (!result.acquirerDomain && result.accounts.length > 0) {
            result.acquirerDomain = result.accounts[0].domain;
            result.acquirerLabel = result.accounts[0].acquirer;
        }
        if (!result.nmid) {
            result.nmid = result.merchantId || result.merchantPan;
        }

        if (result.accounts.length === 0) {
            result.error = 'Missing Merchant Account Information tags (26-51).';
            return result;
        }
        if (!result.merchantName) {
            result.error = 'Missing Merchant Name tag (59).';
            return result;
        }
        if (!result.crcValid) {
            result.error = 'Invalid CRC-16 checksum (Tag 63).';
            return result;
        }

        result.valid = true;
        return result;
    }

    function createCanvasWithSource(source, sx, sy, sw, sh, targetWidth, targetHeight, padding) {
        var pad = padding || 0;
        var canvas = document.createElement('canvas');
        canvas.width = targetWidth + pad * 2;
        canvas.height = targetHeight + pad * 2;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        var isDownscaling = targetWidth < sw || targetHeight < sh;
        ctx.imageSmoothingEnabled = isDownscaling;
        if (isDownscaling && 'imageSmoothingQuality' in ctx) {
            ctx.imageSmoothingQuality = 'high';
        }
        ctx.drawImage(source, sx, sy, sw, sh, pad, pad, targetWidth, targetHeight);
        return { canvas: canvas, ctx: ctx };
    }

    function computeOtsuThreshold(grayBuffer) {
        var hist = new Uint32Array(256);
        for (var i = 0; i < grayBuffer.length; i++) {
            hist[grayBuffer[i]]++;
        }
        var total = grayBuffer.length;
        var sum = 0;
        for (var t = 0; t < 256; t++) {
            sum += t * hist[t];
        }
        var sumB = 0;
        var wB = 0;
        var maxVar = -1;
        var bestThreshold = 128;

        for (var level = 0; level < 256; level++) {
            wB += hist[level];
            if (wB === 0) {
                continue;
            }
            var wF = total - wB;
            if (wF === 0) {
                break;
            }
            sumB += level * hist[level];
            var mB = sumB / wB;
            var mF = (sum - sumB) / wF;
            var betweenVar = wB * wF * (mB - mF) * (mB - mF);
            if (betweenVar > maxVar) {
                maxVar = betweenVar;
                bestThreshold = level;
            }
        }
        return bestThreshold;
    }

    function binarizeImageData(imageData) {
        var data = imageData.data;
        var pixelCount = data.length / 4;
        var gray = new Uint8Array(pixelCount);

        for (var i = 0, p = 0; i < data.length; i += 4, p++) {
            // Composite alpha over white background before grayscale luminance
            var alpha = data[i + 3] / 255;
            var r = data[i] * alpha + 255 * (1 - alpha);
            var g = data[i + 1] * alpha + 255 * (1 - alpha);
            var b = data[i + 2] * alpha + 255 * (1 - alpha);
            gray[p] = Math.round(0.299 * r + 0.587 * g + 0.114 * b);
        }

        var threshold = computeOtsuThreshold(gray);
        var output = new Uint8ClampedArray(data.length);
        for (var k = 0, idx = 0; k < pixelCount; k++, idx += 4) {
            var bw = gray[k] <= threshold ? 0 : 255;
            output[idx] = bw;
            output[idx + 1] = bw;
            output[idx + 2] = bw;
            output[idx + 3] = 255;
        }
        imageData.data.set(output);
        return imageData;
    }

    function runJsQrOnCanvas(canvas, ctx, inversionMode) {
        if (typeof window === 'undefined' || typeof window.jsQR !== 'function') {
            return null;
        }
        var imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var decoded = window.jsQR(imgData.data, canvas.width, canvas.height, {
            inversionAttempts: inversionMode || 'dontInvert'
        });
        return decoded && decoded.data ? decoded.data : null;
    }

    function loadImageElementFromFile(file) {
        return new Promise(function (resolve, reject) {
            if (typeof createImageBitmap === 'function') {
                createImageBitmap(file).then(function (bitmap) {
                    resolve({
                        source: bitmap,
                        width: bitmap.width,
                        height: bitmap.height,
                        cleanup: function () {
                            if (typeof bitmap.close === 'function') {
                                bitmap.close();
                            }
                        }
                    });
                }).catch(function () {
                    fallbackImgLoad();
                });
                return;
            }
            fallbackImgLoad();

            function fallbackImgLoad() {
                var url = URL.createObjectURL(file);
                var img = new Image();
                img.onload = function () {
                    resolve({
                        source: img,
                        width: img.naturalWidth || img.width,
                        height: img.naturalHeight || img.height,
                        cleanup: function () {
                            URL.revokeObjectURL(url);
                        }
                    });
                };
                img.onerror = function () {
                    URL.revokeObjectURL(url);
                    reject(new Error('Could not load selected image file.'));
                };
                img.src = url;
            }
        });
    }

    async function decodeWithBarcodeDetector(target) {
        if (typeof window === 'undefined' || typeof window.BarcodeDetector !== 'function') {
            return null;
        }
        try {
            var detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            var codes = await detector.detect(target);
            if (codes && codes.length > 0 && codes[0].rawValue) {
                return codes[0].rawValue;
            }
        } catch (e) {
            return null;
        }
        return null;
    }

    async function decodeQrFromFile(file) {
        if (!file) {
            throw new Error('No image file selected.');
        }

        var loaded = await loadImageElementFromFile(file);
        try {
            var natW = loaded.width;
            var natH = loaded.height;
            if (!natW || !natH) {
                throw new Error('Image dimensions could not be read.');
            }

            // Native browser BarcodeDetector first
            var nativeRaw = await decodeWithBarcodeDetector(loaded.source);
            if (nativeRaw) {
                var nativeCleaned = cleanQrisString(nativeRaw);
                return {
                    rawText: nativeCleaned,
                    method: 'BarcodeDetector',
                    parsed: parseEmvcoQris(nativeCleaned)
                };
            }

            // Pass 1: Scaled <= 1200px with white quiet-zone padding
            var maxSide = 1200;
            var scale = Math.min(1, maxSide / Math.max(natW, natH));
            var scaledW = Math.max(64, Math.round(natW * scale));
            var scaledH = Math.max(64, Math.round(natH * scale));
            var pad = 40;

            var pass1 = createCanvasWithSource(loaded.source, 0, 0, natW, natH, scaledW, scaledH, pad);

            var nativePadded = await decodeWithBarcodeDetector(pass1.canvas);
            if (nativePadded) {
                var nativePadCleaned = cleanQrisString(nativePadded);
                return {
                    rawText: nativePadCleaned,
                    method: 'BarcodeDetector (padded)',
                    parsed: parseEmvcoQris(nativePadCleaned)
                };
            }

            var rawPass1 = runJsQrOnCanvas(pass1.canvas, pass1.ctx, 'attemptBoth');
            if (rawPass1) {
                var cleaned1 = cleanQrisString(rawPass1);
                return {
                    rawText: cleaned1,
                    method: 'jsQR Pass 1 (original + quiet-zone)',
                    parsed: parseEmvcoQris(cleaned1)
                };
            }

            // Pass 2: High-contrast grayscale + Otsu binarization
            var pass2Data = binarizeImageData(pass1.ctx.getImageData(0, 0, pass1.canvas.width, pass1.canvas.height));
            pass1.ctx.putImageData(pass2Data, 0, 0);
            var rawPass2 = runJsQrOnCanvas(pass1.canvas, pass1.ctx, 'attemptBoth');
            if (rawPass2) {
                var cleaned2 = cleanQrisString(rawPass2);
                return {
                    rawText: cleaned2,
                    method: 'jsQR Pass 2 (high-contrast binarized)',
                    parsed: parseEmvcoQris(cleaned2)
                };
            }

            // Pass 3: Center-cropped & portrait poster crops + quiet-zone + attemptBoth + binarized retry
            var cropSide = Math.max(64, Math.round(Math.min(natW, natH) * 0.68));
            var targetCropSide = Math.min(900, Math.max(360, cropSide));
            var cropBoxes = [
                {
                    label: 'center-crop',
                    sx: Math.max(0, Math.round((natW - cropSide) / 2)),
                    sy: Math.max(0, Math.round((natH - cropSide) / 2)),
                    side: cropSide
                }
            ];

            if (natH > natW * 1.15) {
                var posterSide = Math.max(64, Math.round(natW * 0.72));
                var lowerY = Math.min(Math.max(0, natH - posterSide), Math.round(natH * 0.56 - posterSide / 2));
                cropBoxes.push({
                    label: 'poster-lower-crop',
                    sx: Math.max(0, Math.round((natW - posterSide) / 2)),
                    sy: Math.max(0, lowerY),
                    side: posterSide
                });
            }

            for (var cIdx = 0; cIdx < cropBoxes.length; cIdx += 1) {
                var box = cropBoxes[cIdx];
                var pass3 = createCanvasWithSource(loaded.source, box.sx, box.sy, box.side, box.side, targetCropSide, targetCropSide, pad);
                var rawPass3 = runJsQrOnCanvas(pass3.canvas, pass3.ctx, 'attemptBoth');
                if (rawPass3) {
                    var cleaned3 = cleanQrisString(rawPass3);
                    return {
                        rawText: cleaned3,
                        method: 'jsQR Pass 3 (' + box.label + ')',
                        parsed: parseEmvcoQris(cleaned3)
                    };
                }

                var pass3Bin = binarizeImageData(pass3.ctx.getImageData(0, 0, pass3.canvas.width, pass3.canvas.height));
                pass3.ctx.putImageData(pass3Bin, 0, 0);
                var rawPass3Bin = runJsQrOnCanvas(pass3.canvas, pass3.ctx, 'attemptBoth');
                if (rawPass3Bin) {
                    var cleaned3Bin = cleanQrisString(rawPass3Bin);
                    return {
                        rawText: cleaned3Bin,
                        method: 'jsQR Pass 3 (' + box.label + ' binarized)',
                        parsed: parseEmvcoQris(cleaned3Bin)
                    };
                }
            }

            throw new Error('Could not detect a readable QR code in this image. Try cropping closer to the QR square or paste the EMVCo QRIS string directly.');
        } finally {
            if (typeof loaded.cleanup === 'function') {
                loaded.cleanup();
            }
        }
    }

    return {
        cleanQrisString: cleanQrisString,
        calculateCrc16Ccitt: calculateCrc16Ccitt,
        parseEmvcoTlv: parseEmvcoTlv,
        parseEmvcoQris: parseEmvcoQris,
        resolveAcquirerLabel: resolveAcquirerLabel,
        decodeQrFromFile: decodeQrFromFile
    };
}));
