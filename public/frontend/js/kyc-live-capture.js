/**
 * DigiKash / PayPoint — built-in KYC live camera capture.
 *
 * Document photo (JPEG) + 5-second live video recording for liveness.
 * Captures blobs into hidden file inputs for multipart submit.
 */
(function (window, document) {
    'use strict';

    var JPEG_QUALITY = 0.92;
    var DEFAULT_RECORD_MS = 5000;

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function stopStream(stream) {
        if (!stream) return;
        stream.getTracks().forEach(function (t) {
            try { t.stop(); } catch (e) {}
        });
    }

    function dataUrlToFile(dataUrl, filename) {
        var parts = dataUrl.split(',');
        var mime = (parts[0].match(/:(.*?);/) || [])[1] || 'image/jpeg';
        var bin = atob(parts[1] || '');
        var len = bin.length;
        var u8 = new Uint8Array(len);
        for (var i = 0; i < len; i++) u8[i] = bin.charCodeAt(i);
        return new File([u8], filename, { type: mime });
    }

    function assignFileToInput(input, file) {
        if (!input || !file) return;
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) {
            console.warn('KYC live: could not assign file to input', e);
        }
    }

    function pickRecorderMime() {
        if (typeof MediaRecorder === 'undefined') return '';
        var candidates = [
            'video/webm;codecs=vp9',
            'video/webm;codecs=vp8',
            'video/webm',
            'video/mp4'
        ];
        for (var i = 0; i < candidates.length; i++) {
            if (MediaRecorder.isTypeSupported(candidates[i])) {
                return candidates[i];
            }
        }
        return '';
    }

    function extensionForMime(mime) {
        if (mime && mime.indexOf('mp4') !== -1) return 'mp4';
        return 'webm';
    }

    function KycLiveCapture(root) {
        this.root = root;
        this.stream = null;
        this.mode = null; // 'document' | 'selfie'
        this.recordTimer = null;
        this.progressTimer = null;
        this.recorder = null;
        this.recordChunks = [];
        this.recording = false;
        this.cfg = {
            requireSelfie: root.getAttribute('data-require-selfie') !== '0',
            requireDocument: root.getAttribute('data-require-document') !== '0',
            recordSeconds: parseFloat(root.getAttribute('data-record-seconds') || '5') || 5
        };
        this.recordMs = Math.max(3000, Math.round(this.cfg.recordSeconds * 1000));

        this.els = {
            panel: root,
            video: qs('[data-kyc-live-video]', root),
            canvas: qs('[data-kyc-live-canvas]', root),
            stage: qs('[data-kyc-live-stage]', root),
            error: qs('[data-kyc-live-error]', root),
            progress: qs('[data-kyc-live-progress]', root),
            progressBar: qs('[data-kyc-live-progress-bar]', root),
            hint: qs('[data-kyc-live-hint]', root),
            docPreview: qs('[data-kyc-live-doc-preview]', root),
            selfiePreview: qs('[data-kyc-live-selfie-preview]', root),
            docInput: qs('[data-kyc-live-doc-input]', root),
            selfieInput: qs('[data-kyc-live-selfie-input]', root),
            docReady: qs('[data-kyc-live-doc-ready]', root),
            selfieReady: qs('[data-kyc-live-selfie-ready]', root),
            snapBtn: qs('[data-kyc-live-snap]', root)
        };

        this.bind();
    }

    KycLiveCapture.prototype.bind = function () {
        var self = this;
        qsa('[data-kyc-live-start]', this.root).forEach(function (btn) {
            btn.addEventListener('click', function () {
                self.start(btn.getAttribute('data-kyc-live-start'));
            });
        });
        if (this.els.snapBtn) {
            this.els.snapBtn.addEventListener('click', function () {
                self.snap();
            });
        }
        var cancel = qs('[data-kyc-live-cancel]', this.root);
        if (cancel) {
            cancel.addEventListener('click', function () {
                self.closeStage();
            });
        }
        var retakeDoc = qs('[data-kyc-live-retake-doc]', this.root);
        if (retakeDoc) {
            retakeDoc.addEventListener('click', function () {
                self.clearCapture('document');
                self.start('document');
            });
        }
        var retakeSelfie = qs('[data-kyc-live-retake-selfie]', this.root);
        if (retakeSelfie) {
            retakeSelfie.addEventListener('click', function () {
                self.clearCapture('selfie');
                self.start('selfie');
            });
        }
    };

    KycLiveCapture.prototype.showError = function (msg) {
        if (!this.els.error) return;
        this.els.error.textContent = msg || '';
        this.els.error.hidden = !msg;
    };

    KycLiveCapture.prototype.clearCapture = function (mode) {
        if (mode === 'document') {
            if (this.els.docInput) this.els.docInput.value = '';
            if (this.els.docPreview) {
                this.els.docPreview.removeAttribute('src');
                this.els.docPreview.hidden = true;
            }
            if (this.els.docReady) this.els.docReady.hidden = true;
            var retakeDoc = qs('[data-kyc-live-retake-doc]', this.root);
            if (retakeDoc) retakeDoc.hidden = true;
        } else {
            if (this.els.selfieInput) this.els.selfieInput.value = '';
            if (this.els.selfiePreview) {
                if (this.els.selfiePreview.src) {
                    try { URL.revokeObjectURL(this.els.selfiePreview.src); } catch (e) {}
                }
                this.els.selfiePreview.removeAttribute('src');
                this.els.selfiePreview.hidden = true;
            }
            if (this.els.selfieReady) this.els.selfieReady.hidden = true;
            var retakeSelfie = qs('[data-kyc-live-retake-selfie]', this.root);
            if (retakeSelfie) retakeSelfie.hidden = true;
        }
        this.root.classList.remove('is-' + mode + '-done');
    };

    KycLiveCapture.prototype.setSnapLabel = function (label) {
        if (!this.els.snapBtn) return;
        this.els.snapBtn.textContent = label;
    };

    KycLiveCapture.prototype.start = function (mode) {
        var self = this;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            this.showError('Camera is not supported in this browser. Please upload a file instead.');
            return;
        }

        this.closeStage(false);
        this.mode = mode === 'selfie' ? 'selfie' : 'document';
        this.showError('');

        if (this.mode === 'selfie' && typeof MediaRecorder === 'undefined') {
            this.showError('Live video recording is not supported in this browser.');
            return;
        }

        var facing = this.mode === 'selfie' ? 'user' : 'environment';
        var constraints = {
            audio: false,
            video: {
                facingMode: { ideal: facing },
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
            self.onStreamReady(stream);
        }).catch(function (err) {
            var msg = 'Camera permission was denied or unavailable. You can still upload a file.';
            if (err && err.name === 'NotAllowedError') {
                msg = 'Camera access denied. Allow camera permission, or upload a file instead.';
            } else if (err && err.name === 'NotFoundError') {
                msg = 'No camera found on this device. Please upload a file instead.';
            }
            self.showError(msg);
            if (facing === 'environment') {
                navigator.mediaDevices.getUserMedia({ audio: false, video: true }).then(function (stream) {
                    self.showError('');
                    self.onStreamReady(stream);
                }).catch(function () {});
            }
        });
    };

    KycLiveCapture.prototype.onStreamReady = function (stream) {
        this.stream = stream;
        if (this.els.video) {
            this.els.video.srcObject = stream;
            this.els.video.setAttribute('playsinline', 'true');
            this.els.video.muted = true;
            this.els.video.play().catch(function () {});
        }
        if (this.els.stage) this.els.stage.hidden = false;
        this.root.classList.add('is-capturing');
        this.root.setAttribute('data-kyc-live-mode', this.mode);

        if (this.els.progress) this.els.progress.hidden = true;
        if (this.els.progressBar) this.els.progressBar.style.width = '0%';
        if (this.els.snapBtn) this.els.snapBtn.disabled = false;

        if (this.mode === 'selfie') {
            if (this.els.hint) {
                this.els.hint.textContent = 'Center your face, then tap Record for a ' + this.cfg.recordSeconds + '-second live video.';
            }
            this.setSnapLabel('Record');
        } else {
            if (this.els.hint) {
                this.els.hint.textContent = 'Align your document in the frame, then tap Capture.';
            }
            this.setSnapLabel('Capture');
        }
    };

    KycLiveCapture.prototype.snap = function () {
        if (this.mode === 'selfie') {
            this.startVideoRecord();
            return;
        }
        this.captureDocumentPhoto();
    };

    KycLiveCapture.prototype.captureDocumentPhoto = function () {
        if (!this.els.video || !this.els.canvas) return;

        var video = this.els.video;
        var canvas = this.els.canvas;
        var w = video.videoWidth || 1280;
        var h = video.videoHeight || 720;
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, w, h);

        var dataUrl = canvas.toDataURL('image/jpeg', JPEG_QUALITY);
        var file = dataUrlToFile(dataUrl, 'kyc-live-document-' + Date.now() + '.jpg');

        assignFileToInput(this.els.docInput, file);
        var primaryFile = document.querySelector('#template-details input[type="file"]');
        if (primaryFile && primaryFile !== this.els.docInput) {
            assignFileToInput(primaryFile, file);
        }
        if (this.els.docPreview) {
            this.els.docPreview.src = dataUrl;
            this.els.docPreview.hidden = false;
        }
        if (this.els.docReady) this.els.docReady.hidden = false;
        var retakeDoc = qs('[data-kyc-live-retake-doc]', this.root);
        if (retakeDoc) retakeDoc.hidden = false;
        this.root.classList.add('is-document-done');
        this.closeStage();
    };

    KycLiveCapture.prototype.startVideoRecord = function () {
        var self = this;
        if (this.recording) return;
        if (!this.stream) {
            this.showError('Camera is not ready yet.');
            return;
        }
        if (typeof MediaRecorder === 'undefined') {
            this.showError('Live video recording is not supported in this browser.');
            return;
        }

        var mime = pickRecorderMime();
        try {
            this.recorder = mime
                ? new MediaRecorder(this.stream, { mimeType: mime })
                : new MediaRecorder(this.stream);
        } catch (e) {
            this.showError('Could not start video recording on this device.');
            return;
        }

        this.recordChunks = [];
        this.recording = true;
        if (this.els.snapBtn) this.els.snapBtn.disabled = true;
        if (this.els.progress) this.els.progress.hidden = false;
        if (this.els.progressBar) this.els.progressBar.style.width = '0%';
        if (this.els.hint) {
            this.els.hint.textContent = 'Recording… keep your face in frame for ' + this.cfg.recordSeconds + ' seconds.';
        }
        this.setSnapLabel('Recording…');
        this.root.classList.add('is-recording');

        this.recorder.ondataavailable = function (event) {
            if (event.data && event.data.size > 0) {
                self.recordChunks.push(event.data);
            }
        };

        this.recorder.onerror = function () {
            self.showError('Recording failed. Please try again.');
            self.stopRecording(true);
        };

        this.recorder.onstop = function () {
            self.finishVideoRecord(mime || (self.recorder && self.recorder.mimeType) || 'video/webm');
        };

        try {
            this.recorder.start(200);
        } catch (e) {
            this.showError('Could not start video recording on this device.');
            this.recording = false;
            this.root.classList.remove('is-recording');
            if (this.els.snapBtn) this.els.snapBtn.disabled = false;
            this.setSnapLabel('Record');
            return;
        }

        var startedAt = Date.now();
        clearInterval(this.progressTimer);
        this.progressTimer = setInterval(function () {
            var elapsed = Date.now() - startedAt;
            var pct = Math.min(100, Math.round((elapsed / self.recordMs) * 100));
            if (self.els.progressBar) self.els.progressBar.style.width = pct + '%';
        }, 50);

        clearTimeout(this.recordTimer);
        this.recordTimer = setTimeout(function () {
            self.stopRecording(false);
        }, this.recordMs);
    };

    KycLiveCapture.prototype.stopRecording = function (abort) {
        clearTimeout(this.recordTimer);
        this.recordTimer = null;
        clearInterval(this.progressTimer);
        this.progressTimer = null;
        this.root.classList.remove('is-recording');

        if (!this.recorder) {
            this.recording = false;
            return;
        }

        if (abort) {
            try {
                this.recorder.ondataavailable = null;
                this.recorder.onstop = null;
                if (this.recorder.state !== 'inactive') this.recorder.stop();
            } catch (e) {}
            this.recorder = null;
            this.recordChunks = [];
            this.recording = false;
            if (this.els.snapBtn) this.els.snapBtn.disabled = false;
            this.setSnapLabel('Record');
            return;
        }

        if (this.recorder.state !== 'inactive') {
            try { this.recorder.stop(); } catch (e) {}
        }
    };

    KycLiveCapture.prototype.finishVideoRecord = function (mime) {
        this.recording = false;
        this.recorder = null;

        if (!this.recordChunks.length) {
            this.showError('No video was recorded. Please try again.');
            if (this.els.snapBtn) this.els.snapBtn.disabled = false;
            this.setSnapLabel('Record');
            return;
        }

        var type = mime || 'video/webm';
        var blob = new Blob(this.recordChunks, { type: type });
        this.recordChunks = [];
        var ext = extensionForMime(type);
        var file = new File([blob], 'kyc-live-video-' + Date.now() + '.' + ext, { type: type });

        assignFileToInput(this.els.selfieInput, file);

        if (this.els.selfiePreview) {
            if (this.els.selfiePreview.src) {
                try { URL.revokeObjectURL(this.els.selfiePreview.src); } catch (e) {}
            }
            this.els.selfiePreview.src = URL.createObjectURL(blob);
            this.els.selfiePreview.hidden = false;
            try {
                this.els.selfiePreview.load();
                this.els.selfiePreview.play().catch(function () {});
            } catch (e) {}
        }
        if (this.els.selfieReady) this.els.selfieReady.hidden = false;
        var retakeSelfie = qs('[data-kyc-live-retake-selfie]', this.root);
        if (retakeSelfie) retakeSelfie.hidden = false;
        this.root.classList.add('is-selfie-done');
        this.closeStage();
    };

    KycLiveCapture.prototype.closeStage = function () {
        this.stopRecording(true);
        stopStream(this.stream);
        this.stream = null;
        if (this.els.video) {
            this.els.video.srcObject = null;
        }
        if (this.els.stage) this.els.stage.hidden = true;
        this.root.classList.remove('is-capturing', 'is-recording');
        this.root.removeAttribute('data-kyc-live-mode');
        if (this.els.progress) this.els.progress.hidden = true;
        if (this.els.snapBtn) this.els.snapBtn.disabled = false;
        this.setSnapLabel('Capture');
    };

    KycLiveCapture.prototype.destroy = function () {
        this.closeStage();
    };

    function boot(root) {
        if (!root || root._kycLive) return root._kycLive;
        root._kycLive = new KycLiveCapture(root);
        return root._kycLive;
    }

    function bootAll(scope) {
        qsa('[data-kyc-live]', scope || document).forEach(boot);
    }

    window.DigiKashKycLive = {
        boot: boot,
        bootAll: bootAll
    };

    document.addEventListener('DOMContentLoaded', function () {
        bootAll();
    });
})(window, document);
