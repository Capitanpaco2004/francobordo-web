(function() {
    var boxes = document.querySelectorAll('.otp-box');
    if (!boxes.length) return;

    var hidden = document.getElementById('otp-hidden');
    var form = boxes[0].closest('form');

    function syncHidden() {
        var val = '';
        boxes.forEach(function(b) { val += (b.value || ''); });
        if (hidden) hidden.value = val;
        return val;
    }

    function distribute(code, startIdx) {
        var clean = code.replace(/\D/g, '');
        for (var j = 0; j < clean.length && (startIdx + j) < boxes.length; j++) {
            boxes[startIdx + j].value = clean[j];
            boxes[startIdx + j].classList.add('filled');
        }
        var nextIdx = Math.min(startIdx + clean.length, boxes.length - 1);
        boxes[nextIdx].focus();
        if (syncHidden().length >= 6) form.submit();
    }

    boxes.forEach(function(box, i) {
        box.addEventListener('input', function() {
            var val = this.value.replace(/\D/g, '');
            // Bitwarden o paste: mas de 1 digito -> distribuir desde esta posicion
            if (val.length > 1) {
                this.value = '';
                distribute(val, i);
                return;
            }
            this.value = val;
            if (val) {
                this.classList.add('filled');
                if (i < boxes.length - 1) boxes[i + 1].focus();
            } else {
                this.classList.remove('filled');
            }
            if (syncHidden().length >= 6) form.submit();
        });

        box.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                if (!this.value && i > 0) {
                    boxes[i - 1].value = '';
                    boxes[i - 1].classList.remove('filled');
                    boxes[i - 1].focus();
                    syncHidden();
                } else {
                    this.classList.remove('filled');
                }
            }
            if (e.key === 'ArrowLeft' && i > 0) { e.preventDefault(); boxes[i - 1].focus(); }
            if (e.key === 'ArrowRight' && i < boxes.length - 1) { e.preventDefault(); boxes[i + 1].focus(); }
        });

        box.addEventListener('paste', function(e) {
            e.preventDefault();
            distribute((e.clipboardData || window.clipboardData).getData('text'), i);
        });

        box.addEventListener('focus', function() { this.select(); });
    });

    // Polling: detecta autofill que no dispara input event
    var lastVal = '';
    var pollCount = 0;
    var poller = setInterval(function() {
        var first = boxes[0].value;
        if (first.length > 1 && first !== lastVal) {
            lastVal = first;
            boxes[0].value = '';
            distribute(first, 0);
            clearInterval(poller);
        }
        if (++pollCount > 60) clearInterval(poller);
    }, 100);

    // Boton saveform (en pantalla setup del admin)
    var saveBtn = document.getElementById('saveform');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            syncHidden();
            form.submit();
        });
    }
})();
