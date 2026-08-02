var msT=window.msT||((key,fallback)=>fallback??key);
(() => {
  const body = document.body;
  if (!body.classList.contains('public-album') || body.dataset.albumColors !== '1') return;

  const coverUrl = body.dataset.cover || '';
  if (!coverUrl) return;

  const image = new Image();
  image.decoding = 'async';

  const clamp = value => Math.max(0, Math.min(255, Math.round(value)));
  const rgb = (r, g, b) => `rgb(${clamp(r)} ${clamp(g)} ${clamp(b)})`;

  const apply = async () => {
    try {
      if (typeof image.decode === 'function') await image.decode();
      if (!image.naturalWidth || !image.naturalHeight) return;

      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 48;
      const context = canvas.getContext('2d', {willReadFrequently:true});
      context.drawImage(image, 0, 0, 48, 48);

      const pixels = context.getImageData(0, 0, 48, 48).data;
      let red = 0, green = 0, blue = 0, count = 0;

      for (let index = 0; index < pixels.length; index += 16) {
        if (pixels[index + 3] < 128) continue;
        const r = pixels[index];
        const g = pixels[index + 1];
        const b = pixels[index + 2];

        // Ignore nearly black/white pixels so artwork borders do not dominate.
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        if (max < 18 || min > 238) continue;

        red += r;
        green += g;
        blue += b;
        count++;
      }

      if (!count) return;

      red /= count;
      green /= count;
      blue /= count;

      const luminance = 0.299 * red + 0.587 * green + 0.114 * blue;
      const accentBoost = luminance < 95 ? 1.55 : luminance > 190 ? 0.68 : 1.12;

      const accentR = clamp(red * accentBoost);
      const accentG = clamp(green * accentBoost);
      const accentB = clamp(blue * accentBoost);

      body.style.setProperty('--album-accent', rgb(accentR, accentG, accentB));
      body.style.setProperty('--album-accent-soft', `rgba(${accentR}, ${accentG}, ${accentB}, .22)`);
      body.style.setProperty('--album-accent-faint', `rgba(${accentR}, ${accentG}, ${accentB}, .10)`);
      body.style.setProperty('--album-bg-dark', rgb(red * .18, green * .18, blue * .18));
      body.style.setProperty('--album-bg-mid', rgb(red * .34, green * .34, blue * .34));
      body.style.setProperty('--album-border', `rgba(${accentR}, ${accentG}, ${accentB}, .42)`);

      body.classList.add('album-colors-enabled');
    } catch (_) {
      // Keep the neutral public design if extraction is unavailable.
    }
  };

  image.addEventListener('load', apply, {once:true});
  image.src = coverUrl;
  if (image.complete && image.naturalWidth > 0) apply();
})();