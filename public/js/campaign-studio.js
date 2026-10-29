/**
 * AI Retention Campaign Studio — Alpine factory (no inline x-data JS).
 * Images are compressed client-side so image_data_url stays under the API limit.
 */
(function (global) {
  'use strict';

  var MAX_DATA_URL = 280000;
  var MAX_EDGE = 1200;

  function csrf() {
    var m = document.querySelector('meta[name=csrf-token]');
    return m ? m.getAttribute('content') : '';
  }

  function compressDataUrl(dataUrl) {
    return new Promise(function (resolve) {
      try {
        var img = new Image();
        img.onload = function () {
          var w = img.naturalWidth || img.width;
          var h = img.naturalHeight || img.height;
          if (!w || !h) { resolve(dataUrl); return; }
          var scale = Math.min(1, MAX_EDGE / Math.max(w, h));
          var cw = Math.max(1, Math.round(w * scale));
          var ch = Math.max(1, Math.round(h * scale));
          var canvas = document.createElement('canvas');
          canvas.width = cw;
          canvas.height = ch;
          var ctx = canvas.getContext('2d');
          if (!ctx) { resolve(dataUrl); return; }
          ctx.drawImage(img, 0, 0, cw, ch);
          var quality = 0.82;
          var out = canvas.toDataURL('image/jpeg', quality);
          while (out.length > MAX_DATA_URL && quality > 0.4) {
            quality -= 0.1;
            out = canvas.toDataURL('image/jpeg', quality);
          }
          if (out.length > MAX_DATA_URL) {
            canvas.width = Math.round(cw * 0.7);
            canvas.height = Math.round(ch * 0.7);
            ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            out = canvas.toDataURL('image/jpeg', 0.55);
          }
          resolve(out.length <= MAX_DATA_URL ? out : null);
        };
        img.onerror = function () { resolve(null); };
        img.src = dataUrl;
      } catch (e) {
        resolve(null);
      }
    });
  }

  global.campaignStudio = function campaignStudio() {
    return {
      discount: 20,
      selectedImage: 0,
      customImage: null,
      images: [
        { id: 0, label: 'Warm abstract', color: '#fef3c7' },
        { id: 1, label: 'Product lifestyle', color: '#e0e7ff' },
        { id: 2, label: 'Loyalty badge', color: '#ede9fe' },
      ],
      sending: false,
      sent: false,
      generating: false,
      subject: 'We miss you. Keep your access active',
      bodyLead: 'You have been with us for a long time. Your last payment failed, but your access can still be saved. We held a recovery path open so you do not lose the benefits you already earned - renew in one step when you are ready.',
      previewName: 'there',
      brand: 'your plan',
      tone: 'Empathetic (loyal user)',

      onFile: async function (e) {
        var f = e.target.files && e.target.files[0];
        if (!f) return;
        if (!/^image\//.test(f.type || '')) {
          if (global.LuvuToast) global.LuvuToast.error('Invalid file', 'Please choose a PNG, JPEG, or WebP image');
          return;
        }
        if (f.size > 8 * 1024 * 1024) {
          if (global.LuvuToast) global.LuvuToast.error('Image too large', 'Use an image under 8 MB');
          return;
        }
        var self = this;
        var reader = new FileReader();
        reader.onload = async function (ev) {
          var compressed = await compressDataUrl(String(ev.target.result));
          if (!compressed) {
            if (global.LuvuToast) global.LuvuToast.error('Image too large', 'Could not compress enough. Try a smaller photo.');
            self.customImage = null;
            return;
          }
          self.customImage = compressed;
          self.selectedImage = -1;
        };
        reader.readAsDataURL(f);
      },

      regenerateCopy: async function () {
        this.generating = true;
        await new Promise(function (r) { setTimeout(r, 400); });
        var variants = [
          {
            s: 'Important: keep your ' + this.brand + ' access',
            b: 'Hi ' + this.previewName + ', we noticed a payment issue. Renew now with ' + this.discount + '% off to keep uninterrupted access. We held a recovery path open so you do not lose the benefits you already earned.',
          },
          {
            s: 'We miss you, ' + this.previewName,
            b: 'Hi ' + this.previewName + ', your plan is still recoverable. As a valued member you get ' + this.discount + '% off if you renew today. Finish in one step and keep what you already paid for.',
          },
          {
            s: this.previewName + ', a quick way to restore access',
            b: 'Payment failed once. Finish in one click and keep your benefits. Offer: ' + this.discount + '% off for 24 hours. No need to start over.',
          },
        ];
        var v = variants[Math.floor(Math.random() * variants.length)];
        this.subject = v.s;
        this.bodyLead = v.b;
        this.generating = false;
      },

      approveAndSend: async function () {
        if (this.sending || this.sent) return;
        if (this.customImage && this.customImage.length > MAX_DATA_URL) {
          if (global.LuvuToast) global.LuvuToast.error('Image too large', 'Re-upload a smaller image (auto-compress failed)');
          return;
        }
        this.sending = true;
        if (global.LuvuLoader && global.LuvuLoader.begin) global.LuvuLoader.begin();
        try {
          var res = await fetch('/api/v1/dashboard/demo/retention-campaign', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({
              subject: this.subject,
              body: this.bodyLead || '',
              discount: this.discount,
              tone: this.tone || 'empathetic',
              image_data_url: this.customImage || null,
              cohort: 'failed_and_refund',
            }),
          });
          var data = await res.json().catch(function () { return {}; });
          this.sending = false;
          if (!res.ok || data.ok === false) {
            var msg = data.message || (data.errors && data.errors.image_data_url && data.errors.image_data_url[0]) || 'Check mail config';
            if (/image data url|image_data_url|greater than/i.test(String(msg))) {
              msg = 'Image is still too large after compress. Try a smaller photo.';
            }
            if (global.LuvuToast) global.LuvuToast.error('Send failed', msg);
            return;
          }
          this.sent = true;
          if (global.LuvuToast) {
            global.LuvuToast.success('Campaign sent', data.message || ('Sent to ' + (data.sent || 0) + ' users'));
          }
        } catch (e) {
          this.sending = false;
          if (global.LuvuToast) global.LuvuToast.error('Send failed', String(e.message || e));
        } finally {
          if (global.LuvuLoader && global.LuvuLoader.end) global.LuvuLoader.end();
        }
      },
    };
  };
})(window);
