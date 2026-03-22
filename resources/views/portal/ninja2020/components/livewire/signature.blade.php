<div class="">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-xl text-center py-0 px-4">{{ ctrans('texts.sign_here_ux_tip') }}</h2>
        <canvas id="signature-pad" class="border border-gray-300 w-full h-64"></canvas>
        <div class="flex justify-between items-center px-4 py-4">
            <button id="clear-signature" class="px-4 py-2 mr-6 bg-red-500 text-white rounded">{{ ctrans('texts.clear') }}</button>
            <button id="save-button" class="button button-primary bg-primary hover:bg-primary-darken">{{ ctrans('texts.next') }}</button>
        </div>
    </div>
    
    @assets
    <script src="{{ asset('vendor/signature_pad@5/signature_pad.umd.min.js') }}"></script>
    @endassets
    @script
    <script>
            
            const canvas = document.getElementById('signature-pad');
            const signaturePad = new SignaturePad(canvas);

            // Resize canvas to fit the parent container
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
            }

            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            document.getElementById('save-button').addEventListener('click', function() {
                if (!signaturePad.isEmpty()) {                    
                    $wire.dispatch('signature-captured', {base64: signaturePad.toDataURL()});

                } else {
                    alert('Please provide a signature first.');
                }
            });
 
            document.getElementById('clear-signature').addEventListener('click', function() {
                signaturePad.clear();
            });
           

    </script>
    @endscript
</div>