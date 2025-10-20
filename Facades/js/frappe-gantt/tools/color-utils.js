var ColorUtils = (function () {
  class ColorUtils {

// css-color -> {r,g,b,a} via offscreen element + getComputedStyle
    cssColorToRgba(color) {
      const el = document.createElement('span');
      el.style.color = color;
      // the element must be in the DOM for getComputedStyle to be reliable
      document.body.appendChild(el);
      const cs = getComputedStyle(el).color; // "rgb(r, g, b)" or "rgba(r, g, b, a)"
      document.body.removeChild(el);

      const m = cs.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([.\d]+))?\)/i);
      if (!m) return null;
      const [, r, g, b, a] = m;
      return { r: +r, g: +g, b: +b, a: a !== undefined ? +a : 1 };
    }

    rgbaToHex({r,g,b,a=1}) {
      const h = x => x.toString(16).padStart(2,'0');
      // We ignore a in hex; however, CSS variables can also be assigned rgba().
      return `#${h(r)}${h(g)}${h(b)}`;
    }

    rgbToHsl({r,g,b}) {
      r/=255; g/=255; b/=255;
      const max=Math.max(r,g,b), min=Math.min(r,g,b);
      let h=0, s=0, l=(max+min)/2;
      if (max !== min) {
        const d = max-min;
        s = l>0.5 ? d/(2-max-min) : d/(max+min);
        switch (max) {
          case r: h=(g-b)/d + (g<b?6:0); break;
          case g: h=(b-r)/d + 2; break;
          case b: h=(r-g)/d + 4; break;
        }
        h/=6;
      }
      return { h, s, l };
    }

    hslToRgb({h,s,l}) {
      let r,g,b;
      if (s === 0) { r=g=b=l; }
      else {
        const q = l < .5 ? l*(1+s) : l + s - l*s;
        const p = 2*l - q;
        const hue = t=>{
          if (t<0) t+=1;
          if (t>1) t-=1;
          if (t<1/6) return p + (q-p)*6*t;
          if (t<1/2) return q;
          if (t<2/3) return p + (q-p)*(2/3 - t)*6;
          return p;
        };
        r = hue(h+1/3); g = hue(h); b = hue(h-1/3);
      }
      return { r:Math.round(r*255), g:Math.round(g*255), b:Math.round(b*255) };
    }

// Shift brightness (L) by delta; negative delta = darker
    shadeCssColor(baseColor, deltaL) {
      const rgba = this.cssColorToRgba(baseColor);
      if (!rgba) return baseColor; // Fallback: unverändert
      const hsl = this.rgbToHsl(rgba);
      hsl.l = Math.min(1, Math.max(0, hsl.l + deltaL));
      const rgb = this.hslToRgb(hsl);
      // Wenn die Eingabe alpha hatte, könntest du hier auch rgba(...) zurückgeben.
      return this.rgbaToHex(rgb); // hex ist hier am zuverlässigsten
    }

// public API: derive variants from any CSS colour
    deriveColors(baseCssColor) {
      return {
        color: baseCssColor,                               // Original
        colorHover: this.shadeCssColor(baseCssColor, -0.08),    // slightly darker
        progressColor: this.shadeCssColor(baseCssColor, -0.28), // significantly darker
        //textColor: '#fff',
      };
    }
  }
  return ColorUtils
})();
//# sourceMappingURL=color-utils.js.map