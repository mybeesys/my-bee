@if($qrPayload ?? null)
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
    <script>
        window.__documentQrPayloads = window.__documentQrPayloads || {};
        window.__documentQrPayloads[@json($qrImageId ?? 'document-qr-image')] = @json($qrPayload);

        window.renderDocumentQrFallback = function (imageId) {
            const image = document.getElementById(imageId || 'document-qr-image');
            const payload = window.__documentQrPayloads[imageId || 'document-qr-image'];

            if (!image || !payload || typeof QRCode === 'undefined') {
                return;
            }

            QRCode.toDataURL(payload, {
                width: 120,
                margin: 1,
                errorCorrectionLevel: 'M',
            }, function (error, url) {
                if (!error && url) {
                    image.src = url;
                }
            });
        };

        document.addEventListener('DOMContentLoaded', function () {
            const imageId = @json($qrImageId ?? 'document-qr-image');
            const image = document.getElementById(imageId);

            if (!image || !image.getAttribute('src')) {
                window.renderDocumentQrFallback(imageId);
            }
        });
    </script>
@endif
