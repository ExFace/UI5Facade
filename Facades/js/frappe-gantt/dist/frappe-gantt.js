var Gantt = function() {
  "use strict";
  const YEAR = "year";
  const MONTH = "month";
  const DAY = "day";
  const HOUR = "hour";
  const MINUTE = "minute";
  const SECOND = "second";
  const MILLISECOND = "millisecond";
  const date_utils = {
    parse_duration(duration) {
      const regex = /([0-9]+)(y|m|d|h|min|s|ms)/gm;
      const matches = regex.exec(duration);
      if (matches !== null) {
        if (matches[2] === "y") {
          return { duration: parseInt(matches[1]), scale: `year` };
        } else if (matches[2] === "m") {
          return { duration: parseInt(matches[1]), scale: `month` };
        } else if (matches[2] === "d") {
          return { duration: parseInt(matches[1]), scale: `day` };
        } else if (matches[2] === "h") {
          return { duration: parseInt(matches[1]), scale: `hour` };
        } else if (matches[2] === "min") {
          return { duration: parseInt(matches[1]), scale: `minute` };
        } else if (matches[2] === "s") {
          return { duration: parseInt(matches[1]), scale: `second` };
        } else if (matches[2] === "ms") {
          return { duration: parseInt(matches[1]), scale: `millisecond` };
        }
      }
    },
    parse(date, date_separator = "-", time_separator = /[.:]/) {
      if (date instanceof Date) {
        return date;
      }
      if (typeof date === "string") {
        let date_parts, time_parts;
        const parts = date.split(" ");
        date_parts = parts[0].split(date_separator).map((val) => parseInt(val, 10));
        time_parts = parts[1] && parts[1].split(time_separator);
        date_parts[1] = date_parts[1] ? date_parts[1] - 1 : 0;
        let vals = date_parts;
        if (time_parts && time_parts.length) {
          if (time_parts.length === 4) {
            time_parts[3] = "0." + time_parts[3];
            time_parts[3] = parseFloat(time_parts[3]) * 1e3;
          }
          vals = vals.concat(time_parts);
        }
        return new Date(...vals);
      }
    },
    to_string(date, with_time = false) {
      if (!(date instanceof Date)) {
        throw new TypeError("Invalid argument type");
      }
      const vals = this.get_date_values(date).map((val, i) => {
        if (i === 1) {
          val = val + 1;
        }
        if (i === 6) {
          return padStart(val + "", 3, "0");
        }
        return padStart(val + "", 2, "0");
      });
      const date_string = `${vals[0]}-${vals[1]}-${vals[2]}`;
      const time_string = `${vals[3]}:${vals[4]}:${vals[5]}.${vals[6]}`;
      return date_string + (with_time ? " " + time_string : "");
    },
    // >>> SR: Bar Aggregation -------------------------------------------------
    // TODO SR: Complete the time formating testing and clean the old code here:
    format(date, format_string = "YYYY-MM-dd HH:mm:ss.SSS") {
      return exfTools.date.format(date, format_string);
    },
    /*    format(date, date_format = 'YYYY-MM-DD HH:mm:ss.SSS', lang = 'en') {
                const dateTimeFormat = new Intl.DateTimeFormat(lang, {
                    month: 'long',
                });
                const dateTimeFormatShort = new Intl.DateTimeFormat(lang, { //TODO SR: that is new. Check it.
                    month: 'short',
                });
                const month_name = dateTimeFormat.format(date);
                const month_name_capitalized =
                    month_name.charAt(0).toUpperCase() + month_name.slice(1);
    
                const values = this.get_date_values(date).map((d) => padStart(d, 2, 0));
                const format_map = {
                    YYYY: values[0],
                    MM: padStart(+values[1] + 1, 2, 0),
                    DD: values[2],
                    HH: values[3],
                    mm: values[4],
                    ss: values[5],
                    SSS: values[6],
                    D: values[2],
                    MMMM: month_name_capitalized,
                    MMM: dateTimeFormatShort.format(date), //TODO SR: that is new. Check it.
                };
    
                let str = date_format;
                const formatted_values = [];
    
                Object.keys(format_map)
                    .sort((a, b) => b.length - a.length) // big string first
                    .forEach((key) => {
                        if (str.includes(key)) {
                            str = str.replaceAll(key, `$${formatted_values.length}`); //TODO SR: replaceAll instead of replace is new
                            formatted_values.push(format_map[key]);
                        }
                    });
    
                formatted_values.forEach((value, i) => {
                    str = str.replaceAll(`$${i}`, value);
                });
    
                return str;
            },*/
    // <<< SR: Bar Aggregation -------------------------------------------------
    diff(date_a, date_b, scale = "day") {
      let milliseconds, seconds, hours, minutes, days, months, years;
      milliseconds = date_a - date_b + (date_b.getTimezoneOffset() - date_a.getTimezoneOffset()) * 6e4;
      seconds = milliseconds / 1e3;
      minutes = seconds / 60;
      hours = minutes / 60;
      days = hours / 24;
      let yearDiff = date_a.getFullYear() - date_b.getFullYear();
      let monthDiff = date_a.getMonth() - date_b.getMonth();
      monthDiff += days % 30 / 30;
      months = yearDiff * 12 + monthDiff;
      if (date_a.getDate() < date_b.getDate()) {
        months--;
      }
      years = months / 12;
      if (!scale.endsWith("s")) {
        scale += "s";
      }
      return Math.round(
          {
            milliseconds,
            seconds,
            minutes,
            hours,
            days,
            months,
            years
          }[scale] * 100
      ) / 100;
    },
    today() {
      const vals = this.get_date_values(/* @__PURE__ */ new Date()).slice(0, 3);
      return new Date(...vals);
    },
    now() {
      return /* @__PURE__ */ new Date();
    },
    add(date, qty, scale) {
      qty = parseInt(qty, 10);
      return moment(date).add(qty, `${scale}s`).toDate();
    },
    start_of(date, scale) {
      const scores = {
        [YEAR]: 6,
        [MONTH]: 5,
        [DAY]: 4,
        [HOUR]: 3,
        [MINUTE]: 2,
        [SECOND]: 1,
        [MILLISECOND]: 0
      };
      function should_reset(_scale) {
        const max_score = scores[scale];
        return scores[_scale] <= max_score;
      }
      if (date === void 0) {
        return /* @__PURE__ */ new Date();
      }
      const vals = [
        date.getFullYear(),
        should_reset(YEAR) ? 0 : date.getMonth(),
        should_reset(MONTH) ? 1 : date.getDate(),
        should_reset(DAY) ? 0 : date.getHours(),
        should_reset(HOUR) ? 0 : date.getMinutes(),
        should_reset(MINUTE) ? 0 : date.getSeconds(),
        should_reset(SECOND) ? 0 : date.getMilliseconds()
      ];
      return new Date(...vals);
    },
    clone(date) {
      return new Date(...this.get_date_values(date));
    },
    get_date_values(date) {
      return [
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
        date.getHours(),
        date.getMinutes(),
        date.getSeconds(),
        date.getMilliseconds()
      ];
    },
    convert_scales(period, to_scale) {
      const TO_DAYS = {
        millisecond: 1 / 60 / 60 / 24 / 1e3,
        second: 1 / 60 / 60 / 24,
        minute: 1 / 60 / 24,
        hour: 1 / 24,
        day: 1,
        month: 30,
        year: 365
      };
      const { duration, scale } = this.parse_duration(period);
      let in_days = duration * TO_DAYS[scale];
      return in_days / TO_DAYS[to_scale];
    },
    get_days_in_month(date) {
      const no_of_days = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
      const month = date.getMonth();
      if (month !== 1) {
        return no_of_days[month];
      }
      const year = date.getFullYear();
      if (year % 4 === 0 && year % 100 != 0 || year % 400 === 0) {
        return 29;
      }
      return 28;
    },
    get_days_in_year(date) {
      return date.getFullYear() % 4 ? 365 : 366;
    }
  };
  function padStart(str, targetLength, padString) {
    str = str + "";
    targetLength = targetLength >> 0;
    padString = String(typeof padString !== "undefined" ? padString : " ");
    if (str.length > targetLength) {
      return String(str);
    } else {
      targetLength = targetLength - str.length;
      if (targetLength > padString.length) {
        padString += padString.repeat(targetLength / padString.length);
      }
      return padString.slice(0, targetLength) + String(str);
    }
  }
  function $(expr, con) {
    return typeof expr === "string" ? (con || document).querySelector(expr) : expr || null;
  }
  function createSVG(tag, attrs) {
    const elem = document.createElementNS("http://www.w3.org/2000/svg", tag);
    for (let attr in attrs) {
      if (attr === "append_to") {
        const parent = attrs.append_to;
        parent.appendChild(elem);
      } else if (attr === "innerHTML") {
        elem.innerHTML = attrs.innerHTML;
      } else if (attr === "clipPath") {
        elem.setAttribute("clip-path", "url(#" + attrs[attr] + ")");
      } else {
        elem.setAttribute(attr, attrs[attr]);
      }
    }
    return elem;
  }
  function animateSVG(svgElement, attr, from, to) {
    const animatedSvgElement = getAnimationElement(svgElement, attr, from, to);
    if (animatedSvgElement === svgElement) {
      const event = document.createEvent("HTMLEvents");
      event.initEvent("click", true, true);
      event.eventName = "click";
      animatedSvgElement.dispatchEvent(event);
    }
  }
  function getAnimationElement(svgElement, attr, from, to, dur = "0.4s", begin = "0.1s") {
    const animEl = svgElement.querySelector("animate");
    if (animEl) {
      $.attr(animEl, {
        attributeName: attr,
        from,
        to,
        dur,
        begin: "click + " + begin
        // artificial click
      });
      return svgElement;
    }
    const animateElement = createSVG("animate", {
      attributeName: attr,
      from,
      to,
      dur,
      begin,
      calcMode: "spline",
      values: from + ";" + to,
      keyTimes: "0; 1",
      keySplines: cubic_bezier("ease-out")
    });
    svgElement.appendChild(animateElement);
    return svgElement;
  }
  function cubic_bezier(name) {
    return {
      ease: ".25 .1 .25 1",
      linear: "0 0 1 1",
      "ease-in": ".42 0 1 1",
      "ease-out": "0 0 .58 1",
      "ease-in-out": ".42 0 .58 1"
    }[name];
  }
  $.on = (element, event, selector, callback) => {
    if (!callback) {
      callback = selector;
      $.bind(element, event, callback);
    } else {
      $.delegate(element, event, selector, callback);
    }
  };
  $.off = (element, event, handler) => {
    element.removeEventListener(event, handler);
  };
  $.bind = (element, event, callback) => {
    event.split(/\s+/).forEach(function(event2) {
      element.addEventListener(event2, callback);
    });
  };
  $.delegate = (element, event, selector, callback) => {
    element.addEventListener(event, function(e) {
      const delegatedTarget = e.target.closest(selector);
      if (delegatedTarget) {
        e.delegatedTarget = delegatedTarget;
        callback.call(this, e, delegatedTarget);
      }
    });
  };
  $.closest = (selector, element) => {
    if (!element) return null;
    if (element.matches(selector)) {
      return element;
    }
    return $.closest(selector, element.parentNode);
  };
  $.attr = (element, attr, value) => {
    if (!value && typeof attr === "string") {
      return element.getAttribute(attr);
    }
    if (typeof attr === "object") {
      for (let key in attr) {
        $.attr(element, key, attr[key]);
      }
      return;
    }
    element.setAttribute(attr, value);
  };
  class Arrow {
    constructor(gantt, from_task, to_task) {
      this.gantt = gantt;
      this.from_task = from_task;
      this.to_task = to_task;
      this.calculate_path();
      this.draw();
    }
    calculate_path() {
      let start_x = this.from_task.$bar.getX() + this.from_task.$bar.getWidth() / 2;
      const condition = () => this.to_task.$bar.getX() < start_x + this.gantt.options.padding && start_x > this.from_task.$bar.getX() + this.gantt.options.padding;
      while (condition()) {
        start_x -= 10;
      }
      start_x -= 10;
      const start_y = this.from_task.$bar.getY() + this.from_task.$bar.getHeight() / 2;
      const end_x = this.to_task.$bar.getX() - this.gantt.options.padding / 2;
      const end_y = this.to_task.$bar.getY() + this.to_task.$bar.getHeight() / 2;
      const from_is_below_to = this.from_task.task._index > this.to_task.task._index;
      let curve = this.gantt.options.arrow_curve;
      const clockwise = from_is_below_to ? 1 : 0;
      let curve_y = from_is_below_to ? -curve : curve;
      if (this.to_task.$bar.getX() <= this.from_task.$bar.getX() + this.gantt.options.padding) {
        let down_1 = this.gantt.options.padding / 2 - curve;
        if (down_1 < 0) {
          down_1 = 0;
          curve = this.gantt.options.padding / 2;
          curve_y = from_is_below_to ? -curve : curve;
        }
        const down_2 = this.to_task.$bar.getY() + this.to_task.$bar.getHeight() / 2 - curve_y;
        const left = this.to_task.$bar.getX() - this.gantt.options.padding;
        this.path = `
                M ${start_x} ${start_y}
                v ${down_1}
                a ${curve} ${curve} 0 0 1 ${-curve} ${curve}
                H ${left}
                a ${curve} ${curve} 0 0 ${clockwise} ${-curve} ${curve_y}
                V ${down_2}
                a ${curve} ${curve} 0 0 ${clockwise} ${curve} ${curve_y}
                L ${end_x} ${end_y}
                m -5 -5
                l 5 5
                l -5 5`;
      } else {
        if (end_x < start_x + curve) curve = end_x - start_x;
        let offset = from_is_below_to ? end_y + curve : end_y - curve;
        this.path = `
              M ${start_x} ${start_y}
              V ${offset}
              a ${curve} ${curve} 0 0 ${clockwise} ${curve} ${curve}
              L ${end_x} ${end_y}
              m -5 -5
              l 5 5
              l -5 5`;
      }
    }
    draw() {
      this.element = createSVG("path", {
        d: this.path,
        "data-from": this.from_task.task.id,
        "data-to": this.to_task.task.id
      });
    }
    update() {
      this.calculate_path();
      this.element.setAttribute("d", this.path);
    }
  }
  class Bar {
    constructor(gantt, task) {
      this.set_defaults(gantt, task);
      this.prepare_wrappers();
      this.prepare_helpers();
      this.refresh();
    }
    refresh() {
      this.bar_group.innerHTML = "";
      this.handle_group.innerHTML = "";
      if (this.task.custom_class) {
        this.group.classList.add(this.task.custom_class);
      } else {
        this.group.classList = ["bar-wrapper"];
      }
      this.prepare_values();
      this.draw();
      this.bind();
    }
    set_defaults(gantt, task) {
      this.action_completed = false;
      this.gantt = gantt;
      this.task = task;
      this.name = this.name || "";
    }
    prepare_wrappers() {
      this.group = createSVG("g", {
        class: "bar-wrapper" + (this.task.custom_class ? " " + this.task.custom_class : ""),
        "data-id": this.task.id
      });
      this.bar_group = createSVG("g", {
        class: "bar-group",
        append_to: this.group
      });
      this.handle_group = createSVG("g", {
        class: "handle-group",
        append_to: this.group
      });
    }
    prepare_values() {
      this.invalid = this.task.invalid;
      this.height = this.get_bar_height_for_task(this.task);
      this.image_size = this.height - 5;
      this.task.orig_end = new Date(this.task.end);
      this.compute_x();
      this.compute_y();
      this.compute_duration();
      this.corner_radius = Math.min(this.gantt.options.bar_corner_radius, this.height / 2);
      this.width = this.gantt.config.column_width * this.duration;
      if (!this.task.progress || this.task.progress < 0)
        this.task.progress = 0;
      if (this.task.progress > 100) this.task.progress = 100;
    }
    prepare_helpers() {
      SVGElement.prototype.getX = function() {
        return +this.getAttribute("x");
      };
      SVGElement.prototype.getY = function() {
        return +this.getAttribute("y");
      };
      SVGElement.prototype.getWidth = function() {
        return +this.getAttribute("width");
      };
      SVGElement.prototype.getHeight = function() {
        return +this.getAttribute("height");
      };
      SVGElement.prototype.getEndX = function() {
        return this.getX() + this.getWidth();
      };
    }
    prepare_expected_progress_values() {
      this.compute_expected_progress();
      this.expected_progress_width = this.gantt.options.column_width * this.duration * (this.expected_progress / 100) || 0;
    }
    draw() {
      this.draw_bar();
      this.draw_progress_bar();
      if (this.gantt.options.show_expected_progress) {
        this.prepare_expected_progress_values();
        this.draw_expected_progress_bar();
      }
      this.draw_label();
      this.draw_resize_handles();
      if (this.task.thumbnail) {
        this.draw_thumbnail();
      }
    }
    draw_bar() {
      this.$bar = createSVG("rect", {
        x: this.x,
        y: this.y,
        width: this.width,
        height: this.height,
        rx: this.corner_radius,
        ry: this.corner_radius,
        class: "bar",
        append_to: this.bar_group
      });
      this.set_bar_colors();
      this.build_aggregation_bar();
      animateSVG(this.$bar, "width", 0, this.width);
      if (this.invalid) {
        this.$bar.classList.add("bar-invalid");
      }
    }
    draw_expected_progress_bar() {
      if (this.invalid) return;
      this.$expected_bar_progress = createSVG("rect", {
        x: this.x,
        y: this.y,
        width: this.expected_progress_width,
        height: this.height,
        rx: this.corner_radius,
        ry: this.corner_radius,
        class: "bar-expected-progress",
        append_to: this.bar_group
      });
      animateSVG(
          this.$expected_bar_progress,
          "width",
          0,
          this.expected_progress_width
      );
    }
    draw_progress_bar() {
      if (this.invalid) return;
      this.progress_width = this.calculate_progress_width();
      let r = this.corner_radius;
      if (!/^((?!chrome|android).)*safari/i.test(navigator.userAgent))
        r = this.corner_radius + 2;
      this.$bar_progress = createSVG("rect", {
        x: this.x,
        y: this.y,
        width: this.progress_width,
        height: this.height,
        rx: r,
        ry: r,
        class: "bar-progress",
        append_to: this.bar_group
      });
      if (this.task.color_progress)
        this.$bar_progress.style.fill = this.task.color_progress;
      const x = date_utils.diff(
          this.task._start,
          this.gantt.gantt_start,
          this.gantt.config.unit
      ) / this.gantt.config.step * this.gantt.config.column_width;
      let $date_highlight = this.gantt.create_el({
        classes: `date-range-highlight hide highlight-${this.task.id}`,
        width: this.width,
        left: x
      });
      this.$date_highlight = $date_highlight;
      this.gantt.$lower_header.prepend(this.$date_highlight);
      animateSVG(this.$bar_progress, "width", 0, this.progress_width);
    }
    calculate_progress_width() {
      const width = this.$bar.getWidth();
      const ignored_end = this.x + width;
      const total_ignored_area = this.gantt.config.ignored_positions.reduce((acc, val) => {
        return acc + (val >= this.x && val < ignored_end);
      }, 0) * this.gantt.config.column_width;
      let progress_width = (width - total_ignored_area) * this.task.progress / 100;
      const progress_end = this.x + progress_width;
      const total_ignored_progress = this.gantt.config.ignored_positions.reduce((acc, val) => {
        return acc + (val >= this.x && val < progress_end);
      }, 0) * this.gantt.config.column_width;
      progress_width += total_ignored_progress;
      let ignored_regions = this.gantt.get_ignored_region(
          this.x + progress_width
      );
      while (ignored_regions.length) {
        progress_width += this.gantt.config.column_width;
        ignored_regions = this.gantt.get_ignored_region(
            this.x + progress_width
        );
      }
      this.progress_width = progress_width;
      return progress_width;
    }
    draw_label() {
      let x_coord = this.x + this.$bar.getWidth() / 2;
      if (this.task.thumbnail) {
        x_coord = this.x + this.image_size + 5;
      }
      const $label = createSVG("text", {
        x: x_coord,
        y: this.y + this.height / 2,
        innerHTML: this.task.name,
        class: "bar-label",
        append_to: this.bar_group
      });
      if (this.task.textColor) {
        $label.style.fill = String(this.task.textColor);
      }
      requestAnimationFrame(() => this.update_label_position());
    }
    draw_thumbnail() {
      let x_offset = 10, y_offset = 2;
      let defs, clipPath;
      defs = createSVG("defs", {
        append_to: this.bar_group
      });
      createSVG("rect", {
        id: "rect_" + this.task.id,
        x: this.x + x_offset,
        y: this.y + y_offset,
        width: this.image_size,
        height: this.image_size,
        rx: "15",
        class: "img_mask",
        append_to: defs
      });
      clipPath = createSVG("clipPath", {
        id: "clip_" + this.task.id,
        append_to: defs
      });
      createSVG("use", {
        href: "#rect_" + this.task.id,
        append_to: clipPath
      });
      createSVG("image", {
        x: this.x + x_offset,
        y: this.y + y_offset,
        width: this.image_size,
        height: this.image_size,
        class: "bar-img",
        href: this.task.thumbnail,
        clipPath: "clip_" + this.task.id,
        append_to: this.bar_group
      });
    }
    draw_resize_handles() {
      if (this.invalid || this.gantt.options.readonly) return;
      const bar = this.$bar;
      const handle_width = 3;
      this.handles = [];
      if (!this.gantt.options.readonly_dates) {
        this.handles.push(
            createSVG("rect", {
              x: bar.getEndX() - handle_width / 2,
              y: bar.getY() + this.height / 4,
              width: handle_width,
              height: this.height / 2,
              rx: 2,
              ry: 2,
              class: "handle right",
              append_to: this.handle_group
            })
        );
        this.handles.push(
            createSVG("rect", {
              x: bar.getX() - handle_width / 2,
              y: bar.getY() + this.height / 4,
              width: handle_width,
              height: this.height / 2,
              rx: 2,
              ry: 2,
              class: "handle left",
              append_to: this.handle_group
            })
        );
      }
      if (!this.gantt.options.readonly_progress) {
        const bar_progress = this.$bar_progress;
        this.$handle_progress = createSVG("circle", {
          cx: bar_progress.getEndX(),
          cy: bar_progress.getY() + bar_progress.getHeight() / 2,
          r: 4.5,
          class: "handle progress",
          append_to: this.handle_group
        });
        this.handles.push(this.$handle_progress);
      }
      for (let handle of this.handles) {
        $.on(handle, "mouseenter", () => handle.classList.add("active"));
        $.on(handle, "mouseleave", () => handle.classList.remove("active"));
      }
    }
    bind() {
      if (this.invalid) return;
      this.setup_click_event();
    }
    setup_click_event() {
      let task_id = this.task.id;
      $.on(this.group, "mouseover", (e) => {
        this.gantt.trigger_event("hover", [
          this.task,
          e.screenX,
          e.screenY,
          e
        ]);
      });
      if (this.gantt.options.popup_on === "click") {
        $.on(this.group, "mouseup", (e) => {
          const posX = e.offsetX || e.layerX;
          if (this.$handle_progress) {
            const cx = +this.$handle_progress.getAttribute("cx");
            if (cx > posX - 1 && cx < posX + 1) return;
            if (this.gantt.bar_being_dragged) return;
          }
          this.gantt.show_popup({
            x: e.offsetX || e.layerX,
            y: e.offsetY || e.layerY,
            task: this.task,
            target: this.$bar
          });
        });
      }
      let timeout;
      $.on(this.group, "mouseenter", (e) => {
        timeout = setTimeout(() => {
          if (this.gantt.options.popup_on === "hover")
            this.gantt.show_popup({
              x: e.offsetX || e.layerX,
              y: e.offsetY || e.layerY,
              task: this.task,
              target: this.$bar
            });
          this.gantt.$container.querySelector(`.highlight-${CSS.escape(task_id)}`).classList.remove("hide");
        }, 200);
      });
      $.on(this.group, "mouseleave", () => {
        var _a, _b;
        clearTimeout(timeout);
        if (this.gantt.options.popup_on === "hover")
          (_b = (_a = this.gantt.popup) == null ? void 0 : _a.hide) == null ? void 0 : _b.call(_a);
        this.gantt.$container.querySelector(`.highlight-${CSS.escape(task_id)}`).classList.add("hide");
      });
      $.on(this.group, "click", () => {
        this.gantt.trigger_event("click", [this.task]);
      });
      $.on(this.group, "dblclick", (e) => {
        if (this.action_completed) {
          return;
        }
        this.group.classList.remove("active");
        if (this.gantt.popup)
          this.gantt.popup.parent.classList.remove("hide");
        this.gantt.trigger_event("double_click", [this.task]);
      });
      let tapedTwice = false;
      $.on(this.group, "touchstart", (e) => {
        if (!tapedTwice) {
          tapedTwice = true;
          setTimeout(function() {
            tapedTwice = false;
          }, 300);
          return false;
        }
        e.preventDefault();
        if (this.action_completed) {
          return;
        }
        this.group.classList.remove("active");
        if (this.gantt.popup)
          this.gantt.popup.parent.classList.remove("hide");
        this.gantt.trigger_event("double_click", [this.task]);
      });
    }
    update_bar_position({ x = null, width = null }) {
      const bar = this.$bar;
      if (x) {
        const xs = this.task.dependencies.map((dep) => {
          return this.gantt.get_bar(dep).$bar.getX();
        });
        const valid_x = xs.reduce((prev, curr) => {
          return prev && x >= curr;
        }, true);
        if (!valid_x) return;
        this.update_attr(bar, "x", x);
        this.x = x;
        this.$date_highlight.style.left = x + "px";
      }
      if (width > 0) {
        this.update_attr(bar, "width", width);
        this.$date_highlight.style.width = width + "px";
      }
      this.update_label_position();
      this.update_handle_position();
      this.date_changed();
      this.compute_duration();
      if (this.gantt.options.show_expected_progress) {
        this.update_expected_progressbar_position();
      }
      this.update_progressbar_position();
      this.update_arrow_position();
    }
    //TODO SR: Fix image scroll position.
    update_label_position_on_horizontal_scroll({ x, sx }) {
      const container = this.gantt.$container;
      const label = this.group.querySelector(".bar-label");
      const img = this.group.querySelector(".bar-img") || "";
      const img_mask = this.bar_group.querySelector(".img_mask") || "";
      let barWidthLimit = this.$bar.getX() + this.$bar.getWidth();
      let newLabelX = label.getX() + x;
      let newImgX = img && img.getX() + x || 0;
      let imgWidth = img && img.getBBox().width + 7 || 7;
      let labelEndX = newLabelX + label.getBBox().width + 7;
      let viewportCentral = sx + container.clientWidth / 2;
      if (label.classList.contains("big")) return;
      if (labelEndX < barWidthLimit && x > 0 && labelEndX < viewportCentral) {
        label.setAttribute("x", newLabelX);
        if (img) {
          img.setAttribute("x", newImgX);
          img_mask.setAttribute("x", newImgX);
        }
      } else if (newLabelX - imgWidth > this.$bar.getX() && x < 0 && labelEndX > viewportCentral) {
        label.setAttribute("x", newLabelX);
        if (img) {
          img.setAttribute("x", newImgX);
          img_mask.setAttribute("x", newImgX);
        }
      }
    }
    date_changed() {
      let changed = false;
      const { new_start_date, new_end_date } = this.compute_start_end_date();
      if (Number(this.task._start) !== Number(new_start_date)) {
        changed = true;
        this.task._start = new_start_date;
      }
      if (Number(this.task.orig_end) !== Number(new_end_date)) {
        changed = true;
        this.task.orig_end = new_end_date;
      }
      if (!changed) return;
      this.gantt.trigger_event("date_change", [
        this.task,
        new_start_date,
        date_utils.add(new_end_date, -1, "second")
      ]);
    }
    progress_changed() {
      this.task.progress = this.compute_progress();
      this.gantt.trigger_event("progress_change", [
        this.task,
        this.task.progress
      ]);
    }
    set_action_completed() {
      this.action_completed = true;
      setTimeout(() => this.action_completed = false, 1e3);
    }
    compute_start_end_date() {
      const bar = this.$bar;
      const x_in_units = bar.getX() / this.gantt.config.column_width;
      let new_start_date = date_utils.add(
          this.gantt.gantt_start,
          x_in_units * this.gantt.config.step,
          this.gantt.config.unit
      );
      const width_in_units = bar.getWidth() / this.gantt.config.column_width;
      const new_end_date = date_utils.add(
          new_start_date,
          width_in_units * this.gantt.config.step,
          this.gantt.config.unit
      );
      return { new_start_date, new_end_date };
    }
    compute_progress() {
      this.progress_width = this.$bar_progress.getWidth();
      this.x = this.$bar_progress.getBBox().x;
      const progress_area = this.x + this.progress_width;
      const progress = this.progress_width - this.gantt.config.ignored_positions.reduce((acc, val) => {
        return acc + (val >= this.x && val <= progress_area);
      }, 0) * this.gantt.config.column_width;
      if (progress < 0) return 0;
      const total = this.$bar.getWidth() - this.ignored_duration_raw * this.gantt.config.column_width;
      return parseInt(progress / total * 100, 10);
    }
    compute_expected_progress() {
      this.expected_progress = date_utils.diff(date_utils.today(), this.task._start, "hour") / this.gantt.config.step;
      this.expected_progress = (this.expected_progress < this.duration ? this.expected_progress : this.duration) * 100 / this.duration;
    }
    compute_x() {
      const { column_width } = this.gantt.config;
      const task_start = this.task._start;
      const gantt_start = this.gantt.gantt_start;
      const diff = date_utils.diff(task_start, gantt_start, this.gantt.config.unit) / this.gantt.config.step;
      let x = diff * column_width;
      this.x = x;
    }
    compute_y() {
      const rowIndex = this.task._rowIndex != null ? this.task._rowIndex : this.task._index;
      const lane = this.task._lane != null ? this.task._lane : 0;
      const baseY = this.gantt.config.header_height + //TODO SR INFO: ATTENTION! The incorrect procedure in the old adapted version is as follows: The padding should only change the distance to the header in the first line. 
          // The first line should appear larger accordingly and the others should remain the same. 
          // Since the top line does not change here, all bars automatically slide down. 
          // The problem currently lies in the incorrect calculation of padding in connection with overlapping lanes.
          //TODO SR: The padding already malfunctioned in the old adapted version and needs to be reworked.
          //this.gantt.options.padding + //TODO SR: Put the padding back as soon as the problem has been fixed!
          this.rowTop(rowIndex);
      const innerTop = (this.gantt.options.bar_inner_padding || 0) / 2;
      let y = baseY + innerTop + lane * (this.height + this.gantt.options.lane_padding);
      this.y = y;
    }
    compute_duration() {
      let actual_duration_in_days = 0, duration_in_days = 0;
      for (
          let d = new Date(this.task._start);
          // >>> SR: Bar Aggregation -----------------------------------------
          //d < this.task._end; //TODO SR: Date without hours fix. Test it.
          d < this.task.orig_end;
          // <<< SR: Bar Aggregation -----------------------------------------
          d.setDate(d.getDate() + 1)
      ) {
        duration_in_days++;
        if (!this.gantt.config.ignored_dates.find(
            (k) => k.getTime() === d.getTime()
        ) && (!this.gantt.config.ignored_function || !this.gantt.config.ignored_function(d))) {
          actual_duration_in_days++;
        }
      }
      this.task.actual_duration = actual_duration_in_days;
      this.task.ignored_duration = duration_in_days - actual_duration_in_days;
      this.duration = date_utils.convert_scales(
          duration_in_days + "d",
          this.gantt.config.unit
      ) / this.gantt.config.step;
      this.actual_duration_raw = date_utils.convert_scales(
          actual_duration_in_days + "d",
          this.gantt.config.unit
      ) / this.gantt.config.step;
      this.ignored_duration_raw = this.duration - this.actual_duration_raw;
    }
    update_attr(element, attr, value) {
      value = +value;
      if (!isNaN(value)) {
        element.setAttribute(attr, value);
      }
      return element;
    }
    update_expected_progressbar_position() {
      if (this.invalid) return;
      this.$expected_bar_progress.setAttribute("x", this.$bar.getX());
      this.compute_expected_progress();
      this.$expected_bar_progress.setAttribute(
          "width",
          this.gantt.config.column_width * this.actual_duration_raw * (this.expected_progress / 100) || 0
      );
    }
    update_progressbar_position() {
      if (this.invalid || this.gantt.options.readonly) return;
      this.$bar_progress.setAttribute("x", this.$bar.getX());
      this.$bar_progress.setAttribute(
          "width",
          this.calculate_progress_width()
      );
    }
    update_label_position() {
      const img_mask = this.bar_group.querySelector(".img_mask") || "";
      const bar = this.$bar, label = this.group.querySelector(".bar-label"), img = this.group.querySelector(".bar-img");
      let padding = 5;
      let x_offset_label_img = this.image_size + 10;
      const labelWidth = label.getBBox().width;
      const barWidth = bar.getWidth();
      const overflow = this.gantt.options.label_overflow || "outside";
      const isStacked = (this.task._clusterLanes || 1) > 1;
      const isLowHeight = this.height <= 14;
      label.classList.remove("big");
      label.classList.remove("clip-left");
      label.classList.remove("small");
      if (isStacked || isLowHeight) {
        label.classList.add("small");
      }
      const labelMidStartX = bar.getX() + barWidth / 2 - labelWidth / 2;
      const imgEndX = bar.getX() + x_offset_label_img;
      const imgLabelCollision = img && imgEndX >= labelMidStartX;
      if (!imgLabelCollision && labelWidth <= barWidth) {
        label.classList.remove("big");
        if (img) {
          img.setAttribute("x", bar.getX() + padding);
          img_mask.setAttribute("x", bar.getX() + padding);
        }
        label.setAttribute(
            //TODO SR: New temp fix for image + label collision
            "x",
            labelMidStartX
        );
        label.removeAttribute("clip-path");
        label.style.fill = this.task.textColor;
        return;
      }
      if (overflow === "outside") {
        label.classList.add("big");
        if (img) {
          img.setAttribute("x", bar.getEndX() + padding);
          img_mask.setAttribute("x", bar.getEndX() + padding);
          label.setAttribute("x", bar.getEndX() + x_offset_label_img);
        } else {
          label.setAttribute("x", bar.getEndX() + padding);
        }
        label.removeAttribute("clip-path");
        label.style.fill = String(this.gantt.options.label_outside_color);
      } else if (overflow === "clip") {
        label.classList.remove("big");
        const insetX = 2;
        const insetY = 1;
        if (img) {
          img.setAttribute("x", bar.getX() + padding);
          img_mask.setAttribute("x", bar.getX() + padding);
        }
        label.classList.add("clip-left");
        label.setAttribute("x", bar.getX() + insetX + (img ? x_offset_label_img : 0));
        label.setAttribute("y", bar.getY() + bar.getHeight() / 2);
        const clipId = `clip-label-${String(this.task.id).replace(/[^a-zA-Z0-9_-]/g, "")}`;
        let defs = this.gantt.$svg.querySelector("defs");
        if (!defs) defs = createSVG("defs", { append_to: this.gantt.$svg });
        const old = this.gantt.$svg.querySelector(`#${clipId}`);
        if (old) old.remove();
        const cp = createSVG("clipPath", { id: clipId, append_to: defs });
        createSVG("rect", {
          x: bar.getX() + insetX + (img ? x_offset_label_img : 0),
          y: bar.getY() + insetY,
          width: Math.max(0, bar.getWidth() - (img ? x_offset_label_img : 0) - insetX * 2),
          height: Math.max(0, bar.getHeight() - insetY * 2),
          rx: Math.max(0, this.corner_radius - insetX),
          ry: Math.max(0, this.corner_radius - insetY),
          append_to: cp
        });
        label.setAttribute("clip-path", `url(#${clipId})`);
      }
    }
    update_handle_position() {
      if (this.invalid || this.gantt.options.readonly) return;
      const bar = this.$bar;
      this.handle_group.querySelector(".handle.left").setAttribute("x", bar.getX());
      this.handle_group.querySelector(".handle.right").setAttribute("x", bar.getEndX());
      const handle = this.group.querySelector(".handle.progress");
      handle && handle.setAttribute("cx", this.$bar_progress.getEndX());
    }
    update_arrow_position() {
      this.arrows = this.arrows || [];
      for (let arrow of this.arrows) {
        arrow.update();
      }
    }
    // >>> SR: Bar Aggregation -------------------------------------------------
    /**
     * Aggregation bar buildup
     *
     * here, the Aggregation look is made.
     * It contains all the bars that overlaps more than 2 times with another bars.
     */
    build_aggregation_bar() {
      let defs = this.gantt.$svg.querySelector("defs");
      if (!defs) defs = createSVG("defs", { append_to: this.gantt.$svg });
      const inset = 1.5;
      const clipId = `clip-legend-${String(this.task.id).replace(/[^a-zA-Z0-9_-]/g, "")}`;
      const oldClip = this.gantt.$svg.querySelector(`#${clipId}`);
      if (oldClip) oldClip.remove();
      const $cp = createSVG("clipPath", { id: clipId, append_to: defs });
      createSVG("rect", {
        x: this.x + inset,
        y: this.y + inset,
        width: Math.max(0, this.width - inset * 2),
        height: Math.max(0, this.height - inset * 2),
        rx: Math.max(0, this.corner_radius - inset),
        ry: Math.max(0, this.corner_radius - inset),
        append_to: $cp
      });
      if (this.task._isAggregate && Array.isArray(this.task._members)) {
        const colorSwatches = this.task._members.map((m) => m && m.color).filter(Boolean);
        if (colorSwatches.length) {
          const swatchW = 8;
          const gapX = 1;
          const h = Math.max(0, this.height - inset * 2);
          let xSwatch = this.x + inset;
          const swatchesGroup = createSVG("g", {
            append_to: this.bar_group
          });
          swatchesGroup.setAttribute("clip-path", `url(#${clipId})`);
          colorSwatches.forEach((c) => {
            const r = createSVG("rect", {
              x: xSwatch,
              y: this.y + inset,
              width: swatchW,
              height: h,
              class: "agg-swatch-v",
              append_to: swatchesGroup
            });
            r.setAttribute("fill", c);
            r.setAttribute("pointer-events", "none");
            xSwatch += swatchW + gapX;
            if (xSwatch > this.x + this.width - inset) return;
          });
        }
      }
    }
    /**
     * It sets the bar colors from task properties.
     */
    set_bar_colors() {
      if (this.task.color) {
        this.$bar.style.setProperty("--bar-fill", String(this.task.color));
      }
      if (this.task.colorHover) {
        this.$bar.style.setProperty("--bar-fill-hover", String(this.task.colorHover));
      }
    }
    /**
     * Calculates the bar height for a task, considering cluster lanes and paddings.
     * @param task
     * @returns {number}
     */
    get_bar_height_for_task(task) {
      const lanes = Math.max(1, task._clusterLanes || 1);
      const inner = Math.max(0, this.gantt.options.bar_inner_padding || 0);
      const laneGaps = (lanes - 1) * this.gantt.options.lane_padding;
      const available = this.gantt.options.row_height - inner - laneGaps;
      const h = available / lanes;
      return Math.max(6, h);
    }
    rowTop(rowIndex) {
      var _a;
      return ((_a = this.gantt._rowMeta[rowIndex]) == null ? void 0 : _a.top) || 0;
    }
    // <<< SR: Bar Aggregation -------------------------------------------------
  }
  class Popup {
    constructor(parent, popup_func, gantt) {
      this.parent = parent;
      this.popup_func = popup_func;
      this.gantt = gantt;
      this.make();
    }
    make() {
      this.parent.innerHTML = `
            <div class="title"></div>
            <div class="subtitle"></div>
            <div class="details"></div>
            <div class="actions"></div>
        `;
      this.hide();
      this.title = this.parent.querySelector(".title");
      this.subtitle = this.parent.querySelector(".subtitle");
      this.details = this.parent.querySelector(".details");
      this.actions = this.parent.querySelector(".actions");
    }
    show({ x, y, task, target }) {
      this.actions.innerHTML = "";
      let html = this.popup_func({
        task,
        chart: this.gantt,
        get_title: () => this.title,
        set_title: (title) => this.title.innerHTML = title,
        get_subtitle: () => this.subtitle,
        set_subtitle: (subtitle) => this.subtitle.innerHTML = subtitle,
        get_details: () => this.details,
        set_details: (details) => this.details.innerHTML = details,
        add_action: (html2, func) => {
          let action = this.gantt.create_el({
            classes: "action-btn",
            type: "button",
            append_to: this.actions
          });
          if (typeof html2 === "function") html2 = html2(task);
          action.innerHTML = html2;
          action.onclick = (e) => func(task, this.gantt, e);
        }
      });
      if (html === false) return;
      if (html) this.parent.innerHTML = html;
      if (this.actions.innerHTML === "") this.actions.remove();
      else this.parent.appendChild(this.actions);
      this.clear_aggregation_list();
      const members = task._isAggregate ? task._members || [] : task._aggMembers || [];
      if (members == null ? void 0 : members.length) {
        this.parent.querySelector(".details").innerHTML = "";
        this.parent.appendChild(
            this.build_aggregation_list(members)
        );
      }
      this.parent.style.left = x + 10 + "px";
      this.parent.style.top = y - 10 + "px";
      this.parent.classList.remove("hide");
    }
    hide() {
      this.parent.classList.add("hide");
    }
    // >>> SR: Bar Aggregation ---------------------------------------------------
    /**
     * Builds the aggregation list for given aggregation members.
     *
     * @param members
     * @returns {HTMLUListElement}
     */
    build_aggregation_list(members) {
      const ul = document.createElement("ul");
      ul.className = "agg-list";
      members.forEach((m) => {
        const li = document.createElement("li");
        const swatch = document.createElement("span");
        swatch.className = "agg-color-swatch";
        if (m.color) {
          swatch.style.backgroundColor = String(m.color);
        }
        li.appendChild(swatch);
        let ogTask = this.gantt.get_task ? this.gantt.get_task(m.id) : null;
        this.compute_duration(ogTask);
        let labelText = m.name;
        let rangeText = "";
        const start_date = date_utils.format(
            m._start,
            "MMM dd",
            this.gantt.options.language
        );
        let org_end = new Date(m.end);
        const end_date = date_utils.format(
            //date_utils.add(m._end, -1, 'second'),
            date_utils.add(org_end, -1, "second"),
            //TODO SR: Date without hours fix. Test it.
            "MMM dd",
            this.gantt.options.language
        );
        if (m._start && m._end) {
          rangeText = `${start_date} - ${end_date} (${ogTask.actual_duration} Tage${ogTask.ignored_duration ? " + " + ogTask.ignored_duration + " Ausgeschlossen" : ""})`;
        }
        const textSpan = document.createElement("span");
        textSpan.textContent = labelText + " [ " + rangeText + " ]";
        li.appendChild(textSpan);
        ul.appendChild(li);
      });
      return ul;
    }
    /**
     * Removes existing old aggregation list.
     */
    clear_aggregation_list() {
      var _a;
      (_a = this.parent.querySelector(".agg-list")) == null ? void 0 : _a.remove();
    }
    compute_duration(task) {
      if (task == null) return;
      task.orig_end = new Date(task.end);
      let actual_duration_in_days = 0, duration_in_days = 0;
      for (
          let d = new Date(task._start);
          //d < task._end;
          d < task.orig_end;
          //TODO SR: Date without hours fix. Test it.
          d.setDate(d.getDate() + 1)
      ) {
        duration_in_days++;
        if (!this.gantt.config.ignored_dates.find(
            (k) => k.getTime() === d.getTime()
        ) && (!this.gantt.config.ignored_function || !this.gantt.config.ignored_function(d))) {
          actual_duration_in_days++;
        }
      }
      task.actual_duration = actual_duration_in_days;
      task.ignored_duration = duration_in_days - actual_duration_in_days;
    }
    // <<< SR: Bar Aggregation
  }
  function getDecade(d) {
    const year = d.getFullYear();
    return String(year - year % 10);
  }
  function formatWeek(d, ld, lang) {
    let endOfWeek = date_utils.add(d, 6, "day");
    let endFormat = endOfWeek.getMonth() !== d.getMonth() ? "dd MMM" : "dd";
    let beginFormat = !ld || d.getMonth() !== ld.getMonth() ? "dd MMM" : "dd";
    return `${date_utils.format(d, beginFormat, lang)} - ${date_utils.format(endOfWeek, endFormat, lang)}`;
  }
  const DEFAULT_VIEW_MODES = [
    // >>> SR: Bar Aggregation -------------------------------------------------
    // It currently doesn't work properly with PowerUI
    /*    {
            name: 'Hour',
            padding: '7d',
            step: '1h',
            date_format: 'YYYY-MM-dd HH:',
            lower_text: 'HH',
            upper_text: (d, ld, lang) =>
                !ld || d.getDate() !== ld.getDate()
                    ? date_utils.format(d, 'dd MMMM', lang)
                    : '',
            upper_text_frequency: 24,
        },
        {
            name: 'Quarter Day',
            padding: '7d',
            step: '6h',
            date_format: 'YYYY-MM-dd HH:',
            lower_text: 'HH',
            upper_text: (d, ld, lang) =>
                !ld || d.getDate() !== ld.getDate()
                    ? date_utils.format(d, 'dd MMM', lang)
                    : '',
            upper_text_frequency: 4,
        },
        {
            name: 'Half Day',
            padding: '14d',
            step: '12h',
            date_format: 'YYYY-MM-dd HH:',
            lower_text: 'HH',
            upper_text: (d, ld, lang) =>
                !ld || d.getDate() !== ld.getDate()
                    ? d.getMonth() !== d.getMonth()
                        ? date_utils.format(d, 'dd MMM', lang)
                        : date_utils.format(d, 'dd', lang)
                    : '',
            upper_text_frequency: 2,
        },*/
    // <<< SR: Bar Aggregation -------------------------------------------------
    {
      name: "Day",
      padding: "7d",
      date_format: "YYYY-MM-dd",
      step: "1d",
      lower_text: (d, ld, lang) => !ld || d.getDate() !== ld.getDate() ? date_utils.format(d, "dd", lang) : "",
      upper_text: (d, ld, lang) => !ld || d.getMonth() !== ld.getMonth() ? date_utils.format(d, "MMMM", lang) : "",
      thick_line: (d) => d.getDay() === 1
    },
    {
      name: "Week",
      padding: "1m",
      step: "7d",
      date_format: "YYYY-MM-dd",
      column_width: 140,
      lower_text: formatWeek,
      upper_text: (d, ld, lang) => !ld || d.getMonth() !== ld.getMonth() ? date_utils.format(d, "MMMM", lang) : "",
      thick_line: (d) => d.getDate() >= 1 && d.getDate() <= 7,
      upper_text_frequency: 4
    },
    {
      name: "Month",
      padding: "2m",
      step: "1m",
      column_width: 120,
      date_format: "YYYY-MM",
      lower_text: "MMMM",
      upper_text: (d, ld, lang) => !ld || d.getFullYear() !== ld.getFullYear() ? date_utils.format(d, "YYYY", lang) : "",
      thick_line: (d) => d.getMonth() % 3 === 0,
      snap_at: "7d"
    },
    {
      name: "Year",
      padding: "2y",
      step: "1y",
      column_width: 120,
      date_format: "YYYY",
      upper_text: (d, ld, lang) => !ld || getDecade(d) !== getDecade(ld) ? getDecade(d) : "",
      lower_text: "YYYY",
      snap_at: "30d"
    }
  ];
  const DEFAULT_OPTIONS = {
    //TODO SR Info: This is the old "default_options"
    arrow_curve: 5,
    auto_move_label: false,
    bar_corner_radius: 3,
    bar_height: 30,
    //TODO SR Info: The height of the individual bars
    container_height: "auto",
    column_width: null,
    date_format: "YYYY-MM-dd HH:mm",
    upper_header_height: 45,
    //TODO SR: There is no longer a ‘header_height’. Now it is "upper + lower + 10px"
    lower_header_height: 30,
    snap_at: null,
    infinite_padding: true,
    holidays: { "var(--g-weekend-highlight-color)": "weekend" },
    ignore: [],
    language: "en",
    lines: "both",
    move_dependencies: true,
    //TODO SR INFO: The padding here is the padding from the bar to the top and bottom edges of the line. 
    // With the new overlap logic, the padding no longer works. The logic from "Changed" version is still faulty and needs to be revised.
    padding: 18,
    popup: (ctx) => {
      ctx.set_title(ctx.task.name);
      if (ctx.task.description) ctx.set_subtitle(ctx.task.description);
      else ctx.set_subtitle("");
      const start_date = date_utils.format(
          ctx.task._start,
          "MMM dd",
          ctx.chart.options.language
      );
      const end_date = date_utils.format(
          //date_utils.add(ctx.task._end, -1, 'second'),
          date_utils.add(ctx.task.orig_end, -1, "second"),
          "MMM dd",
          ctx.chart.options.language
      );
      ctx.set_details(
          `${start_date} - ${end_date} (${ctx.task.actual_duration} days${ctx.task.ignored_duration ? " + " + ctx.task.ignored_duration + " excluded" : ""})<br/>Progress: ${Math.floor(ctx.task.progress * 100) / 100}%`
      );
    },
    popup_on: "click",
    readonly_progress: false,
    readonly_dates: false,
    readonly: false,
    scroll_to: "today",
    show_expected_progress: false,
    today_button: true,
    view_mode: "Day",
    view_mode_select: false,
    view_modes: DEFAULT_VIEW_MODES,
    is_weekend: (d) => d.getDay() === 0 || d.getDay() === 6,
    // >>> SR: Bar Aggregation -------------------------------------------------
    label_overflow: "outside",
    // 'outside' | 'clip' //TODO SR: The “hide” option has been removed for now.
    label_outside_color: "#555",
    keep_scroll_position: false,
    //TODO SR: Take a look at the new ‘maintain_pos’ in Bar. Maybe this is unnecessary here.
    lane_padding: 4,
    // vertical distance between lanes in the same row
    row_height: null,
    //is calculated automatically, if set to null. //TODO SR: Check whether this should also depend on the view_mode.
    bar_inner_padding: 6,
    // Total vertical padding within the row for each task
    row_keys: null,
    // For empty lines
    tick_color_thick: null
    // Defines the color of the thick tick lines.
    // <<< SR: Bar Aggregation -------------------------------------------------
  };
  class Gantt2 {
    constructor(wrapper, tasks, options) {
      this.setup_wrapper(wrapper);
      this.setup_options(options);
      this.setup_tasks(tasks);
      this.change_view_mode();
      this.bind_events();
    }
    setup_wrapper(element) {
      let svg_element, wrapper_element;
      if (typeof element === "string") {
        let el = document.querySelector(element);
        if (!el) {
          throw new ReferenceError(
              `CSS selector "${element}" could not be found in DOM`
          );
        }
        element = el;
      }
      if (element instanceof HTMLElement) {
        wrapper_element = element;
        svg_element = element.querySelector("svg");
      } else if (element instanceof SVGElement) {
        svg_element = element;
      } else {
        throw new TypeError(
            "Frappe Gantt only supports usage of a string CSS selector, HTML DOM element or SVG DOM element for the 'element' parameter"
        );
      }
      if (!svg_element) {
        this.$svg = createSVG("svg", {
          append_to: wrapper_element,
          class: "gantt"
        });
      } else {
        this.$svg = svg_element;
        this.$svg.classList.add("gantt");
      }
      this.$container = this.create_el({
        classes: "gantt-container",
        append_to: this.$svg.parentElement
      });
      this.$container.appendChild(this.$svg);
      this.$popup_wrapper = this.create_el({
        classes: "popup-wrapper",
        append_to: this.$container
      });
      this._initialScroll = true;
    }
    setup_options(options) {
      this.original_options = options;
      if (options == null ? void 0 : options.view_modes) {
        options.view_modes = options.view_modes.map((mode) => {
          if (typeof mode === "string") {
            const predefined_mode = DEFAULT_VIEW_MODES.find(
                (d) => d.name === mode
            );
            if (!predefined_mode)
              console.error(
                  `The view mode "${mode}" is not predefined in Frappe Gantt. Please define the view mode object instead.`
              );
            return predefined_mode;
          }
          return mode;
        });
        options.view_mode = options.view_modes[0];
      }
      this.options = { ...DEFAULT_OPTIONS, ...options };
      if (this.options.row_height == null) {
        this.options.row_height = this.options.bar_height + this.options.padding;
      }
      if (this.options.bar_inner_padding == null) {
        this.options.bar_inner_padding = 6;
      }
      const CSS_VARIABLES = {
        "grid-height": "container_height",
        "bar-height": "bar_height",
        "lower-header-height": "lower_header_height",
        "upper-header-height": "upper_header_height"
      };
      for (let name in CSS_VARIABLES) {
        let setting = this.options[CSS_VARIABLES[name]];
        if (setting !== "auto")
          this.$container.style.setProperty(
              "--gv-" + name,
              setting + "px"
          );
      }
      this.config = {
        ignored_dates: [],
        ignored_positions: [],
        extend_by_units: 10
      };
      if (typeof this.options.ignore !== "function") {
        if (typeof this.options.ignore === "string")
          this.options.ignore = [this.options.ignore];
        for (let option of this.options.ignore) {
          if (typeof option === "function") {
            this.config.ignored_function = option;
            continue;
          }
          if (typeof option === "string") {
            if (option === "weekend")
              this.config.ignored_function = (d) => d.getDay() == 6 || d.getDay() == 0;
            else this.config.ignored_dates.push(/* @__PURE__ */ new Date(option + " "));
          }
        }
      } else {
        this.config.ignored_function = this.options.ignore;
      }
    }
    update_options(options) {
      this.setup_options({ ...this.original_options, ...options });
      this.change_view_mode(void 0, true);
    }
    setup_tasks(tasks) {
      this.tasks = tasks.map((task, i) => {
        if (!task.start) {
          console.error(
              `task "${task.id}" doesn't have a start date`
          );
          return false;
        }
        task._start = date_utils.parse(task.start);
        if (task.end === void 0 && task.duration !== void 0) {
          task.end = task._start;
          let durations = task.duration.split(" ");
          durations.forEach((tmpDuration) => {
            let { duration, scale } = date_utils.parse_duration(tmpDuration);
            task.end = date_utils.add(task.end, duration, scale);
          });
        }
        if (!task.end) {
          console.error(`task "${task.id}" doesn't have an end date`);
          return false;
        }
        task._end = date_utils.parse(task.end);
        let diff = date_utils.diff(task._end, task._start, "year");
        if (diff < 0) {
          console.error(
              `start of task can't be after end of task: in task "${task.id}"`
          );
          return false;
        }
        if (date_utils.diff(task._end, task._start, "year") > 10) {
          console.error(
              `the duration of task "${task.id}" is too long (above ten years)`
          );
          return false;
        }
        task._index = i;
        const task_end_values = date_utils.get_date_values(task._end);
        if (task_end_values.slice(3).every((d) => d === 0)) {
          task._end = date_utils.add(task._end, 24, "hour");
        }
        if (typeof task.dependencies === "string" || !task.dependencies) {
          let deps = [];
          if (task.dependencies) {
            deps = task.dependencies.split(",").map((d) => d.trim().replaceAll(" ", "_")).filter((d) => d);
          }
          task.dependencies = deps;
        }
        if (!task.id) {
          task.id = generate_id(task);
        } else if (typeof task.id === "string") {
          task.id = task.id.replaceAll(" ", "_");
        } else {
          task.id = `${task.id}`;
        }
        return task;
      }).filter((t) => t);
      this.setup_dependencies();
      this.compute_rows_and_lanes();
      this.compute_overlap_aggregates();
      this.relayout_visible_rows();
    }
    setup_dependencies() {
      this.dependency_map = {};
      for (let t of this.tasks) {
        for (let d of t.dependencies) {
          this.dependency_map[d] = this.dependency_map[d] || [];
          this.dependency_map[d].push(t.id);
        }
      }
    }
    refresh(tasks) {
      this.setup_tasks(tasks);
      this.change_view_mode();
    }
    update_task(id, new_details) {
      let task = this.tasks.find((t) => t.id === id);
      let bar = this.bars[task._index];
      Object.assign(task, new_details);
      bar.refresh();
    }
    change_view_mode(mode = this.options.view_mode, maintain_pos = false) {
      if (typeof mode === "string") {
        mode = this.options.view_modes.find((d) => d.name === mode);
      }
      let old_pos, old_scroll_op;
      if (maintain_pos) {
        old_pos = this.$container.scrollLeft;
        old_scroll_op = this.options.scroll_to;
        this.options.scroll_to = null;
      }
      this.options.view_mode = mode.name;
      this.config.view_mode = mode;
      this.update_view_scale(mode);
      this.setup_dates(maintain_pos);
      this.render();
      if (maintain_pos) {
        this.$container.scrollLeft = old_pos;
        this.options.scroll_to = old_scroll_op;
      }
      this.trigger_event("view_change", [mode]);
    }
    update_view_scale(mode) {
      let { duration, scale } = date_utils.parse_duration(mode.step);
      this.config.step = duration;
      this.config.unit = scale;
      this.config.column_width = this.options.column_width || mode.column_width || 45;
      this.$container.style.setProperty(
          "--gv-column-width",
          this.config.column_width + "px"
      );
      this.config.header_height = this.options.lower_header_height + this.options.upper_header_height + 10;
    }
    setup_dates(refresh = false) {
      this.setup_gantt_dates(refresh);
      this.setup_date_values();
    }
    setup_gantt_dates(refresh) {
      let gantt_start, gantt_end;
      if (!this.tasks.length) {
        gantt_start = /* @__PURE__ */ new Date();
        gantt_end = /* @__PURE__ */ new Date();
      }
      for (let task of this.tasks) {
        if (!gantt_start || task._start < gantt_start) {
          gantt_start = task._start;
        }
        if (!gantt_end || task._end > gantt_end) {
          gantt_end = task._end;
        }
      }
      gantt_start = date_utils.start_of(gantt_start, this.config.unit);
      gantt_end = date_utils.start_of(gantt_end, this.config.unit);
      if (!refresh) {
        if (!this.options.infinite_padding) {
          if (typeof this.config.view_mode.padding === "string")
            this.config.view_mode.padding = [
              this.config.view_mode.padding,
              this.config.view_mode.padding
            ];
          let [padding_start, padding_end] = this.config.view_mode.padding.map(
              date_utils.parse_duration
          );
          this.gantt_start = date_utils.add(
              gantt_start,
              -padding_start.duration,
              padding_start.scale
          );
          this.gantt_end = date_utils.add(
              gantt_end,
              padding_end.duration,
              padding_end.scale
          );
        } else {
          this.gantt_start = date_utils.add(
              gantt_start,
              -this.config.extend_by_units * 3,
              this.config.unit
          );
          this.gantt_end = date_utils.add(
              gantt_end,
              this.config.extend_by_units * 3,
              this.config.unit
          );
        }
      }
      this.config.date_format = this.config.view_mode.date_format || this.options.date_format;
      this.gantt_start.setHours(0, 0, 0, 0);
    }
    setup_date_values() {
      let cur_date = this.gantt_start;
      this.dates = [cur_date];
      while (cur_date < this.gantt_end) {
        cur_date = date_utils.add(
            cur_date,
            this.config.step,
            this.config.unit
        );
        this.dates.push(cur_date);
      }
    }
    bind_events() {
      this.bind_grid_click();
      this.bind_holiday_labels();
      this.bind_bar_events();
      this.bind_outside_click();
    }
    render() {
      this.clear();
      this.setup_layers();
      this.make_grid();
      this.make_dates();
      this.make_grid_extras();
      this.make_bars();
      this.make_arrows();
      this.map_arrows_on_bars();
      this.set_dimensions();
      this.set_scroll_strategy(this.options.scroll_to);
    }
    setup_layers() {
      this.layers = {};
      const layers = ["grid", "arrow", "progress", "bar"];
      for (let layer of layers) {
        this.layers[layer] = createSVG("g", {
          class: layer,
          append_to: this.$svg
        });
      }
      this.$extras = this.create_el({
        classes: "extras",
        append_to: this.$container
      });
      this.$adjust = this.create_el({
        classes: "adjust hide",
        append_to: this.$extras,
        type: "button"
      });
      this.$adjust.innerHTML = "&larr;";
    }
    make_grid() {
      this.make_grid_background();
      this.make_grid_rows();
      this.make_grid_header();
      this.make_side_header();
    }
    make_grid_extras() {
      this.make_grid_highlights();
      this.make_grid_ticks();
    }
    make_grid_background() {
      const grid_width = this.dates.length * this.config.column_width;
      const grid_height = Math.max(
          this.config.header_height + this.options.padding + this.get_content_height(),
          this.options.container_height !== "auto" ? this.options.container_height : 0
      );
      createSVG("rect", {
        x: 0,
        y: 0,
        width: grid_width,
        height: grid_height,
        class: "grid-background",
        append_to: this.$svg
      });
      $.attr(this.$svg, {
        height: grid_height,
        width: "100%"
      });
      this.grid_height = grid_height;
      if (this.options.container_height === "auto")
        this.$container.style.height = grid_height + "px";
    }
    make_grid_rows() {
      const rows_layer = createSVG("g", { append_to: this.layers.grid });
      const row_width = this.dates.length * this.config.column_width;
      const row_height = this.options.bar_height + this.options.padding;
      this.config.header_height;
      for (let y = this.config.header_height; y < this.grid_height; y += row_height) {
        createSVG("rect", {
          x: 0,
          y,
          width: row_width,
          height: row_height,
          class: "grid-row",
          append_to: rows_layer
        });
      }
    }
    make_grid_header() {
      this.$header = this.create_el({
        width: this.dates.length * this.config.column_width,
        classes: "grid-header",
        append_to: this.$container
      });
      this.$upper_header = this.create_el({
        classes: "upper-header",
        append_to: this.$header
      });
      this.$lower_header = this.create_el({
        classes: "lower-header",
        append_to: this.$header
      });
    }
    make_side_header() {
      this.$side_header = this.create_el({ classes: "side-header" });
      this.$upper_header.prepend(this.$side_header);
      if (this.options.view_mode_select) {
        const $select = document.createElement("select");
        $select.classList.add("viewmode-select");
        const $el = document.createElement("option");
        $el.selected = true;
        $el.disabled = true;
        $el.textContent = "Mode";
        $select.appendChild($el);
        for (const mode of this.options.view_modes) {
          const $option = document.createElement("option");
          $option.value = mode.name;
          $option.textContent = mode.name;
          if (mode.name === this.config.view_mode.name)
            $option.selected = true;
          $select.appendChild($option);
        }
        $select.addEventListener(
            "change",
            (function() {
              this.change_view_mode($select.value, true);
            }).bind(this)
        );
        this.$side_header.appendChild($select);
      }
      if (this.options.today_button) {
        let $today_button = document.createElement("button");
        $today_button.classList.add("today-button");
        $today_button.textContent = "Today";
        $today_button.onclick = this.scroll_current.bind(this);
        this.$side_header.prepend($today_button);
        this.$today_button = $today_button;
      }
    }
    make_grid_ticks() {
      if (this.options.lines === "none") return;
      let tick_x = 0;
      let tick_y = this.config.header_height;
      let tick_height = this.grid_height - this.config.header_height;
      let $lines_layer = createSVG("g", {
        class: "lines_layer",
        append_to: this.layers.grid
      });
      let row_y = this.config.header_height;
      const row_width = this.dates.length * this.config.column_width;
      const row_height = this.options.bar_height + this.options.padding;
      if (this.options.lines !== "vertical") {
        for (let y = this.config.header_height; y < this.grid_height; y += row_height) {
          createSVG("line", {
            x1: 0,
            y1: row_y + row_height,
            x2: row_width,
            y2: row_y + row_height,
            class: "row-line",
            append_to: $lines_layer
          });
          row_y += row_height;
        }
      }
      if (this.options.lines === "horizontal") return;
      if (this.config.view_mode.thick_line_color) {
        this.$container.style.setProperty("--g-tick-color-thick", String(this.config.view_mode.thick_line_color));
      }
      for (let date of this.dates) {
        let tick_class = "tick";
        if (this.config.view_mode.thick_line && this.config.view_mode.thick_line(date)) {
          tick_class += " thick";
        }
        createSVG("path", {
          d: `M ${tick_x} ${tick_y} v ${tick_height}`,
          class: tick_class,
          append_to: this.layers.grid
        });
        if (this.view_is("month")) {
          tick_x += date_utils.get_days_in_month(date) * this.config.column_width / 30;
        } else if (this.view_is("year")) {
          tick_x += date_utils.get_days_in_year(date) * this.config.column_width / 365;
        } else {
          tick_x += this.config.column_width;
        }
      }
    }
    highlight_holidays() {
      let labels = {};
      if (!this.options.holidays) return;
      for (let color in this.options.holidays) {
        let check_highlight = this.options.holidays[color];
        if (check_highlight === "weekend")
          check_highlight = this.options.is_weekend;
        let extra_func;
        if (typeof check_highlight === "object") {
          let f = check_highlight.find((k) => typeof k === "function");
          if (f) {
            extra_func = f;
          }
          if (this.options.holidays.name) {
            let dateObj = /* @__PURE__ */ new Date(check_highlight.date + " ");
            check_highlight = (d) => dateObj.getTime() === d.getTime();
            labels[dateObj] = check_highlight.name;
          } else {
            check_highlight = (d) => this.options.holidays[color].filter((k) => typeof k !== "function").map((k) => {
              if (k.name) {
                let dateObj = /* @__PURE__ */ new Date(k.date + " ");
                labels[dateObj] = k.name;
                return dateObj.getTime();
              }
              return (/* @__PURE__ */ new Date(k + " ")).getTime();
            }).includes(d.getTime());
          }
        }
        for (let d = new Date(this.gantt_start); d <= this.gantt_end; d.setDate(d.getDate() + 1)) {
          if (this.config.ignored_dates.find(
              (k) => k.getTime() == d.getTime()
          ) || this.config.ignored_function && this.config.ignored_function(d))
            continue;
          if (check_highlight(d) || extra_func && extra_func(d)) {
            const x = date_utils.diff(
                d,
                this.gantt_start,
                this.config.unit
            ) / this.config.step * this.config.column_width;
            const height = this.grid_height - this.config.header_height;
            const d_formatted = date_utils.format(d, "YYYY-MM-dd", this.options.language).replace(" ", "_");
            if (labels[d]) {
              let label = this.create_el({
                classes: "holiday-label label_" + d_formatted,
                append_to: this.$extras
              });
              label.textContent = labels[d];
            }
            createSVG("rect", {
              x: Math.round(x),
              y: this.config.header_height,
              width: this.config.column_width / date_utils.convert_scales(
                  this.config.view_mode.step,
                  "day"
              ),
              height,
              class: "holiday-highlight " + d_formatted,
              style: `fill: ${color};`,
              append_to: this.layers.grid
            });
          }
        }
      }
    }
    /**
     * Compute the horizontal x-axis distance and associated date for the current date and view.
     *
     * @returns Object containing the x-axis distance and date of the current date, or null if the current date is out of the gantt range.
     */
    highlight_current() {
      const res = this.get_closest_date();
      if (!res) return;
      const [_, el] = res;
      el.classList.add("current-date-highlight");
      const diff_in_units = date_utils.diff(
          /* @__PURE__ */ new Date(),
          this.gantt_start,
          this.config.unit
      );
      const left = diff_in_units / this.config.step * this.config.column_width;
      this.$current_highlight = this.create_el({
        top: this.config.header_height,
        left,
        height: this.grid_height - this.config.header_height,
        classes: "current-highlight",
        append_to: this.$container
      });
      this.$current_ball_highlight = this.create_el({
        top: this.config.header_height - 6,
        left: left - 2.5,
        width: 6,
        height: 6,
        classes: "current-ball-highlight",
        append_to: this.$header
      });
    }
    make_grid_highlights() {
      this.highlight_holidays();
      this.config.ignored_positions = [];
      const height = (this.options.bar_height + this.options.padding) * this.tasks.length;
      this.layers.grid.innerHTML += `<pattern id="diagonalHatch" patternUnits="userSpaceOnUse" width="4" height="4">
          <path d="M-1,1 l2,-2
                   M0,4 l4,-4
                   M3,5 l2,-2"
                style="stroke:grey; stroke-width:0.3" />
        </pattern>`;
      for (let d = new Date(this.gantt_start); d <= this.gantt_end; d.setDate(d.getDate() + 1)) {
        if (!this.config.ignored_dates.find(
            (k) => k.getTime() == d.getTime()
        ) && (!this.config.ignored_function || !this.config.ignored_function(d)))
          continue;
        let diff = date_utils.convert_scales(
            date_utils.diff(d, this.gantt_start) + "d",
            this.config.unit
        ) / this.config.step;
        this.config.ignored_positions.push(diff * this.config.column_width);
        createSVG("rect", {
          x: diff * this.config.column_width,
          y: this.config.header_height,
          width: this.config.column_width,
          height,
          class: "ignored-bar",
          style: "fill: url(#diagonalHatch);",
          append_to: this.$svg
        });
      }
      const highlightDimensions = this.highlight_current(
          this.config.view_mode
      );
      if (!highlightDimensions) return;
    }
    create_el({ left, top, width, height, id, classes, append_to, type }) {
      let $el = document.createElement(type || "div");
      for (let cls of classes.split(" ")) $el.classList.add(cls);
      $el.style.top = top + "px";
      $el.style.left = left + "px";
      if (id) $el.id = id;
      if (width) $el.style.width = width + "px";
      if (height) $el.style.height = height + "px";
      if (append_to) append_to.appendChild($el);
      return $el;
    }
    make_dates() {
      this.get_dates_to_draw().forEach((date, i) => {
        if (date.lower_text) {
          let $lower_text = this.create_el({
            left: date.x,
            top: date.lower_y,
            classes: "lower-text date_" + sanitize(date.formatted_date),
            append_to: this.$lower_header
          });
          $lower_text.innerText = date.lower_text;
        }
        if (date.upper_text) {
          let $upper_text = this.create_el({
            left: date.x,
            top: date.upper_y,
            classes: "upper-text",
            append_to: this.$upper_header
          });
          $upper_text.innerText = date.upper_text;
        }
      });
      this.upperTexts = Array.from(
          this.$container.querySelectorAll(".upper-text")
      );
    }
    get_dates_to_draw() {
      let last_date_info = null;
      const dates = this.dates.map((date, i) => {
        const d = this.get_date_info(date, last_date_info, i);
        last_date_info = d;
        return d;
      });
      return dates;
    }
    get_date_info(date, last_date_info) {
      let last_date = last_date_info ? last_date_info.date : null;
      this.config.column_width;
      const x = last_date_info ? last_date_info.x + last_date_info.column_width : 0;
      let upper_text = this.config.view_mode.upper_text;
      let lower_text = this.config.view_mode.lower_text;
      if (!upper_text) {
        this.config.view_mode.upper_text = () => "";
      } else if (typeof upper_text === "string") {
        this.config.view_mode.upper_text = (date2) => date_utils.format(date2, upper_text, this.options.language);
      }
      if (!lower_text) {
        this.config.view_mode.lower_text = () => "";
      } else if (typeof lower_text === "string") {
        this.config.view_mode.lower_text = (date2) => date_utils.format(date2, lower_text, this.options.language);
      }
      return {
        date,
        formatted_date: sanitize(
            date_utils.format(
                date,
                this.config.date_format,
                this.options.language
            )
        ),
        column_width: this.config.column_width,
        x,
        upper_text: this.config.view_mode.upper_text(
            date,
            last_date,
            this.options.language
        ),
        lower_text: this.config.view_mode.lower_text(
            date,
            last_date,
            this.options.language
        ),
        upper_y: 17,
        lower_y: this.options.upper_header_height + 5
      };
    }
    make_bars() {
      const renderTasks = this.tasks.filter((t) => !t._hidden).concat(this._aggregateBars || []);
      renderTasks.sort((a, b) => {
        const ra = (a._rowIndex ?? a._index) - (b._rowIndex ?? b._index);
        if (ra !== 0) return ra;
        const la = a._lane ?? 0, lb = b._lane ?? 0;
        if (la !== lb) return lb - la;
        if (+a._start !== +b._start) return +a._start - +b._start;
        const ia = isFinite(+a.id) ? +a.id : String(a.id);
        const ib = isFinite(+b.id) ? +b.id : String(b.id);
        return ia > ib ? 1 : ia < ib ? -1 : 0;
      });
      this.bars = renderTasks.map((task) => {
        const bar = new Bar(this, task);
        this.layers.bar.appendChild(bar.group);
        return bar;
      });
    }
    make_arrows() {
      this.arrows = [];
      if (!this.bars || !this.bars.length) return;
      const barById = /* @__PURE__ */ new Map();
      for (const bar of this.bars) {
        if (bar && bar.task && bar.task.id != null) {
          barById.set(bar.task.id, bar);
        }
      }
      for (const task of this.tasks) {
        if (!task || !Array.isArray(task.dependencies) || !task.dependencies.length) continue;
        const toBar = barById.get(task.id);
        if (!toBar) continue;
        for (const depId of task.dependencies) {
          const depTask = this.get_task(depId);
          if (!depTask) continue;
          const fromBar = barById.get(depTask.id);
          if (!fromBar) continue;
          const arrow = new Arrow(this, fromBar, toBar);
          this.layers.arrow.appendChild(arrow.element);
          this.arrows.push(arrow);
        }
      }
    }
    map_arrows_on_bars() {
      for (let bar of this.bars) {
        bar.arrows = this.arrows.filter((arrow) => {
          return arrow.from_task.task.id === bar.task.id || arrow.to_task.task.id === bar.task.id;
        });
      }
    }
    set_dimensions() {
      const { width: cur_width } = this.$svg.getBoundingClientRect();
      const actual_width = this.$svg.querySelector(".grid .grid-row") ? this.$svg.querySelector(".grid .grid-row").getAttribute("width") : 0;
      if (cur_width < actual_width) {
        this.$svg.setAttribute("width", actual_width);
      }
    }
    set_scroll_position(date) {
      if (this.options.infinite_padding && (!date || date === "start")) {
        let [min_start, ..._] = this.get_start_end_positions();
        this.$container.scrollLeft = min_start;
        return;
      }
      if (!date || date === "start") {
        date = this.gantt_start;
      } else if (date === "end") {
        date = this.gantt_end;
      } else if (date === "today") {
        return this.scroll_current();
      } else if (typeof date === "string") {
        date = date_utils.parse(date);
      }
      const units_since_first_task = date_utils.diff(
          date,
          this.gantt_start,
          this.config.unit
      );
      const scroll_pos = units_since_first_task / this.config.step * this.config.column_width;
      this.$container.scrollTo({
        left: scroll_pos - this.config.column_width / 6,
        behavior: "smooth"
      });
      if (this.$current) {
        this.$current.classList.remove("current-upper");
      }
      this.current_date = date_utils.add(
          this.gantt_start,
          this.$container.scrollLeft / this.config.column_width,
          this.config.unit
      );
      let current_upper = this.config.view_mode.upper_text(
          this.current_date,
          null,
          this.options.language
      );
      let $el = this.upperTexts.find(
          (el) => el.textContent === current_upper
      );
      this.current_date = date_utils.add(
          this.gantt_start,
          (this.$container.scrollLeft + $el.clientWidth) / this.config.column_width,
          this.config.unit
      );
      current_upper = this.config.view_mode.upper_text(
          this.current_date,
          null,
          this.options.language
      );
      $el = this.upperTexts.find((el) => el.textContent === current_upper);
      $el.classList.add("current-upper");
      this.$current = $el;
    }
    scroll_current() {
      let res = this.get_closest_date();
      if (res) this.set_scroll_position(res[0]);
    }
    get_closest_date() {
      let now = /* @__PURE__ */ new Date();
      if (now < this.gantt_start || now > this.gantt_end) return null;
      let current = /* @__PURE__ */ new Date(), el = this.$container.querySelector(
          ".date_" + sanitize(
              date_utils.format(
                  current,
                  this.config.date_format,
                  this.options.language
              )
          )
      );
      let c = 0;
      while (!el && c < this.config.step) {
        current = date_utils.add(current, -1, this.config.unit);
        el = this.$container.querySelector(
            ".date_" + sanitize(
                date_utils.format(
                    current,
                    this.config.date_format,
                    this.options.language
                )
            )
        );
        c++;
      }
      return [
        /* @__PURE__ */ new Date(
            date_utils.format(
                current,
                this.config.date_format,
                this.options.language
            ) + " "
        ),
        el
      ];
    }
    bind_grid_click() {
      $.on(
          this.$container,
          "click",
          ".grid-row, .grid-header, .ignored-bar, .holiday-highlight",
          () => {
            this.unselect_all();
            this.hide_popup();
          }
      );
    }
    bind_holiday_labels() {
      const $highlights = this.$container.querySelectorAll(".holiday-highlight");
      for (let h of $highlights) {
        const label = this.$container.querySelector(
            ".label_" + h.classList[1]
        );
        if (!label) continue;
        let timeout;
        h.onmouseenter = (e) => {
          timeout = setTimeout(() => {
            label.classList.add("show");
            label.style.left = (e.offsetX || e.layerX) + "px";
            label.style.top = (e.offsetY || e.layerY) + "px";
          }, 300);
        };
        h.onmouseleave = (e) => {
          clearTimeout(timeout);
          label.classList.remove("show");
        };
      }
    }
    get_start_end_positions() {
      if (!this.bars.length) return [0, 0, 0];
      let { x, width } = this.bars[0].group.getBBox();
      let min_start = x;
      let max_start = x;
      let max_end = x + width;
      Array.prototype.forEach.call(this.bars, function({ group }, i) {
        let { x: x2, width: width2 } = group.getBBox();
        if (x2 < min_start) min_start = x2;
        if (x2 > max_start) max_start = x2;
        if (x2 + width2 > max_end) max_end = x2 + width2;
      });
      return [min_start, max_start, max_end];
    }
    bind_bar_events() {
      let is_dragging = false;
      let x_on_start = 0;
      let x_on_scroll_start = 0;
      let is_resizing_left = false;
      let is_resizing_right = false;
      let parent_bar_id = null;
      let bars = [];
      this.bar_being_dragged = null;
      const action_in_progress = () => is_dragging || is_resizing_left || is_resizing_right;
      this.$svg.onclick = (e) => {
        if (e.target.classList.contains("grid-row")) this.unselect_all();
      };
      let pos = 0;
      $.on(this.$svg, "mousemove", ".bar-wrapper, .handle", (e) => {
        if (this.bar_being_dragged === false && Math.abs((e.offsetX || e.layerX) - pos) > 10)
          this.bar_being_dragged = true;
      });
      $.on(this.$svg, "mousedown", ".bar-wrapper, .handle", (e, element) => {
        const bar_wrapper = $.closest(".bar-wrapper", element);
        if (element.classList.contains("left")) {
          is_resizing_left = true;
          element.classList.add("visible");
        } else if (element.classList.contains("right")) {
          is_resizing_right = true;
          element.classList.add("visible");
        } else if (element.classList.contains("bar-wrapper")) {
          is_dragging = true;
        }
        if (this.popup) this.popup.hide();
        x_on_start = e.offsetX || e.layerX;
        parent_bar_id = bar_wrapper.getAttribute("data-id");
        let ids;
        if (this.options.move_dependencies) {
          ids = [
            parent_bar_id,
            ...this.get_all_dependent_tasks(parent_bar_id)
          ];
        } else {
          ids = [parent_bar_id];
        }
        bars = ids.map((id) => this.get_bar(id));
        this.bar_being_dragged = false;
        pos = x_on_start;
        bars.forEach((bar) => {
          const $bar = bar.$bar;
          $bar.ox = $bar.getX();
          $bar.oy = $bar.getY();
          $bar.owidth = $bar.getWidth();
          $bar.finaldx = 0;
        });
      });
      if (this.options.infinite_padding) {
        let extended = false;
        $.on(this.$container, "mousewheel", (e) => {
          let trigger = this.$container.scrollWidth / 2;
          if (!extended && e.currentTarget.scrollLeft <= trigger) {
            let old_scroll_left = e.currentTarget.scrollLeft;
            extended = true;
            this.gantt_start = date_utils.add(
                this.gantt_start,
                -this.config.extend_by_units,
                this.config.unit
            );
            this.setup_date_values();
            this.render();
            e.currentTarget.scrollLeft = old_scroll_left + this.config.column_width * this.config.extend_by_units;
            setTimeout(() => extended = false, 300);
          }
          if (!extended && e.currentTarget.scrollWidth - (e.currentTarget.scrollLeft + e.currentTarget.clientWidth) <= trigger) {
            let old_scroll_left = e.currentTarget.scrollLeft;
            extended = true;
            this.gantt_end = date_utils.add(
                this.gantt_end,
                this.config.extend_by_units,
                this.config.unit
            );
            this.setup_date_values();
            this.render();
            e.currentTarget.scrollLeft = old_scroll_left;
            setTimeout(() => extended = false, 300);
          }
        });
      }
      $.on(this.$container, "scroll", (e) => {
        let localBars = [];
        const ids = this.bars.map(
            ({ group }) => group.getAttribute("data-id")
        );
        let dx;
        if (x_on_scroll_start) {
          dx = e.currentTarget.scrollLeft - x_on_scroll_start;
        }
        this.current_date = date_utils.add(
            this.gantt_start,
            e.currentTarget.scrollLeft / this.config.column_width * this.config.step,
            this.config.unit
        );
        let current_upper = this.config.view_mode.upper_text(
            this.current_date,
            null,
            this.options.language
        );
        let $el = this.upperTexts.find(
            (el) => el.textContent === current_upper
        );
        this.current_date = date_utils.add(
            this.gantt_start,
            (e.currentTarget.scrollLeft + $el.clientWidth) / this.config.column_width * this.config.step,
            this.config.unit
        );
        current_upper = this.config.view_mode.upper_text(
            this.current_date,
            null,
            this.options.language
        );
        $el = this.upperTexts.find(
            (el) => el.textContent === current_upper
        );
        if ($el !== this.$current) {
          if (this.$current)
            this.$current.classList.remove("current-upper");
          $el.classList.add("current-upper");
          this.$current = $el;
        }
        x_on_scroll_start = e.currentTarget.scrollLeft;
        let [min_start, max_start, max_end] = this.get_start_end_positions();
        if (x_on_scroll_start > max_end + 100) {
          this.$adjust.innerHTML = "&larr;";
          this.$adjust.classList.remove("hide");
          this.$adjust.onclick = () => {
            this.$container.scrollTo({
              left: max_start,
              behavior: "smooth"
            });
          };
        } else if (x_on_scroll_start + e.currentTarget.offsetWidth < min_start - 100) {
          this.$adjust.innerHTML = "&rarr;";
          this.$adjust.classList.remove("hide");
          this.$adjust.onclick = () => {
            this.$container.scrollTo({
              left: min_start,
              behavior: "smooth"
            });
          };
        } else {
          this.$adjust.classList.add("hide");
        }
        if (dx) {
          localBars = ids.map((id) => this.get_bar(id));
          if (this.options.auto_move_label) {
            localBars.forEach((bar) => {
              bar.update_label_position_on_horizontal_scroll({
                x: dx,
                sx: e.currentTarget.scrollLeft
              });
            });
          }
        }
      });
      $.on(this.$svg, "mousemove", (e) => {
        if (!action_in_progress()) return;
        const dx = (e.offsetX || e.layerX) - x_on_start;
        bars.forEach((bar) => {
          const $bar = bar.$bar;
          $bar.finaldx = this.get_snap_position(dx, $bar.ox);
          this.hide_popup();
          if (is_resizing_left) {
            if (parent_bar_id === bar.task.id) {
              bar.update_bar_position({
                x: $bar.ox + $bar.finaldx,
                width: $bar.owidth - $bar.finaldx
              });
            } else {
              bar.update_bar_position({
                x: $bar.ox + $bar.finaldx
              });
            }
          } else if (is_resizing_right) {
            if (parent_bar_id === bar.task.id) {
              bar.update_bar_position({
                width: $bar.owidth + $bar.finaldx
              });
            }
          } else if (is_dragging && !this.options.readonly && !this.options.readonly_dates) {
            bar.update_bar_position({ x: $bar.ox + $bar.finaldx });
          }
        });
      });
      document.addEventListener("mouseup", () => {
        var _a, _b, _c;
        is_dragging = false;
        is_resizing_left = false;
        is_resizing_right = false;
        (_c = (_b = (_a = this.$container.querySelector(".visible")) == null ? void 0 : _a.classList) == null ? void 0 : _b.remove) == null ? void 0 : _c.call(_b, "visible");
      });
      $.on(this.$svg, "mouseup", (e) => {
        this.bar_being_dragged = null;
        bars.forEach((bar) => {
          const $bar = bar.$bar;
          if (!$bar.finaldx) return;
          bar.date_changed();
          bar.compute_progress();
          bar.set_action_completed();
        });
      });
      this.bind_bar_progress();
    }
    bind_bar_progress() {
      let x_on_start = 0;
      let is_resizing = null;
      let bar = null;
      let $bar_progress = null;
      let $bar = null;
      $.on(this.$svg, "mousedown", ".handle.progress", (e, handle) => {
        is_resizing = true;
        x_on_start = e.offsetX || e.layerX;
        const $bar_wrapper = $.closest(".bar-wrapper", handle);
        const id = $bar_wrapper.getAttribute("data-id");
        bar = this.get_bar(id);
        $bar_progress = bar.$bar_progress;
        $bar = bar.$bar;
        $bar_progress.finaldx = 0;
        $bar_progress.owidth = $bar_progress.getWidth();
        $bar_progress.min_dx = -$bar_progress.owidth;
        $bar_progress.max_dx = $bar.getWidth() - $bar_progress.getWidth();
      });
      const range_positions = this.config.ignored_positions.map((d) => [
        d,
        d + this.config.column_width
      ]);
      $.on(this.$svg, "mousemove", (e) => {
        if (!is_resizing) return;
        let now_x = e.offsetX || e.layerX;
        let moving_right = now_x > x_on_start;
        if (moving_right) {
          let k = range_positions.find(
              ([begin, end]) => now_x >= begin && now_x < end
          );
          while (k) {
            now_x = k[1];
            k = range_positions.find(
                ([begin, end]) => now_x >= begin && now_x < end
            );
          }
        } else {
          let k = range_positions.find(
              ([begin, end]) => now_x > begin && now_x <= end
          );
          while (k) {
            now_x = k[0];
            k = range_positions.find(
                ([begin, end]) => now_x > begin && now_x <= end
            );
          }
        }
        let dx = now_x - x_on_start;
        if (dx > $bar_progress.max_dx) {
          dx = $bar_progress.max_dx;
        }
        if (dx < $bar_progress.min_dx) {
          dx = $bar_progress.min_dx;
        }
        $bar_progress.setAttribute("width", $bar_progress.owidth + dx);
        $.attr(bar.$handle_progress, "cx", $bar_progress.getEndX());
        $bar_progress.finaldx = dx;
      });
      $.on(this.$svg, "mouseup", () => {
        is_resizing = false;
        if (!($bar_progress && $bar_progress.finaldx)) return;
        $bar_progress.finaldx = 0;
        bar.progress_changed();
        bar.set_action_completed();
        bar = null;
        $bar_progress = null;
        $bar = null;
      });
    }
    get_all_dependent_tasks(task_id) {
      let out = [];
      let to_process = [task_id];
      while (to_process.length) {
        const deps = to_process.reduce((acc, curr) => {
          acc = acc.concat(this.dependency_map[curr]);
          return acc;
        }, []);
        out = out.concat(deps);
        to_process = deps.filter((d) => !to_process.includes(d));
      }
      return out.filter(Boolean);
    }
    get_snap_position(dx, ox) {
      let unit_length = 1;
      const default_snap = this.options.snap_at || this.config.view_mode.snap_at || "1d";
      if (default_snap !== "unit") {
        const { duration, scale } = date_utils.parse_duration(default_snap);
        unit_length = date_utils.convert_scales(this.config.view_mode.step, scale) / duration;
      }
      const rem = dx % (this.config.column_width / unit_length);
      let final_dx = dx - rem + (rem < this.config.column_width / unit_length * 2 ? 0 : this.config.column_width / unit_length);
      let final_pos = ox + final_dx;
      const drn = final_dx > 0 ? 1 : -1;
      let ignored_regions = this.get_ignored_region(final_pos, drn);
      while (ignored_regions.length) {
        final_pos += this.config.column_width * drn;
        ignored_regions = this.get_ignored_region(final_pos, drn);
        if (!ignored_regions.length)
          final_pos -= this.config.column_width * drn;
      }
      return final_pos - ox;
    }
    get_ignored_region(pos, drn = 1) {
      if (drn === 1) {
        return this.config.ignored_positions.filter((val) => {
          return pos > val && pos <= val + this.config.column_width;
        });
      } else {
        return this.config.ignored_positions.filter(
            (val) => pos >= val && pos < val + this.config.column_width
        );
      }
    }
    unselect_all() {
      if (this.popup) this.popup.parent.classList.add("hide");
      this.$container.querySelectorAll(".date-range-highlight").forEach((k) => k.classList.add("hide"));
    }
    view_is(modes) {
      if (typeof modes === "string") {
        return this.config.view_mode.name === modes;
      }
      if (Array.isArray(modes)) {
        return modes.some(view_is);
      }
      return this.config.view_mode.name === modes.name;
    }
    get_task(id) {
      return this.tasks.find((task) => {
        return task.id === id;
      });
    }
    get_bar(id) {
      return this.bars.find((bar) => {
        return bar.task.id === id;
      });
    }
    show_popup(opts) {
      if (this.options.popup === false) return;
      if (!this.popup) {
        this.popup = new Popup(
            this.$popup_wrapper,
            this.options.popup,
            this
        );
      }
      this.popup.show(opts);
    }
    hide_popup() {
      this.popup && this.popup.hide();
    }
    trigger_event(event, args) {
      if (this.options["on_" + event]) {
        this.options["on_" + event].apply(this, args);
      }
    }
    /**
     * Gets the oldest starting date from the list of tasks
     *
     * @returns Date
     * @memberof Gantt
     */
    get_oldest_starting_date() {
      if (!this.tasks.length) return /* @__PURE__ */ new Date();
      return this.tasks.map((task) => task._start).reduce(
          (prev_date, cur_date) => cur_date <= prev_date ? cur_date : prev_date
      );
    }
    /**
     * Clear all elements from the parent svg element
     *
     * @memberof Gantt
     */
    clear() {
      var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j;
      this.$svg.innerHTML = "";
      (_b = (_a = this.$header) == null ? void 0 : _a.remove) == null ? void 0 : _b.call(_a);
      (_d = (_c = this.$side_header) == null ? void 0 : _c.remove) == null ? void 0 : _d.call(_c);
      (_f = (_e = this.$current_highlight) == null ? void 0 : _e.remove) == null ? void 0 : _f.call(_e);
      (_h = (_g = this.$extras) == null ? void 0 : _g.remove) == null ? void 0 : _h.call(_g);
      (_j = (_i = this.popup) == null ? void 0 : _i.hide) == null ? void 0 : _j.call(_i);
    }
    // >>> SR: Bar Aggregation ---------------------------------------------------
    /**
     * It computes the row and lane allocation for all tasks.
     */
    compute_rows_and_lanes() {
      this.tasks.forEach((t) => {
        t._rowKey = t.lineIndex !== void 0 ? t.lineIndex : t._index;
      });
      const rowMap = /* @__PURE__ */ new Map();
      this.tasks.forEach((t) => {
        if (!rowMap.has(t._rowKey)) rowMap.set(t._rowKey, []);
        rowMap.get(t._rowKey).push(t);
      });
      let rows;
      if (Array.isArray(this.options.row_keys) && this.options.row_keys.length) {
        rows = this.options.row_keys.slice();
      } else {
        rows = Array.from(rowMap.keys()).sort(
            (a, b) => a > b ? 1 : a < b ? -1 : 0
        );
      }
      const rowMeta = [];
      rows.forEach((rowKey, rowIndex) => {
        const list = (rowMap.get(rowKey) || []).slice().sort((a, b) => +a._start - +b._start);
        const laneEnds = [];
        list.forEach((task) => {
          let lane = 0;
          while (lane < laneEnds.length && !(laneEnds[lane] <= task._start)) lane++;
          task._lane = lane;
          task._rowIndex = rowIndex;
          laneEnds[lane] = task._end;
        });
        list.forEach((task) => {
          const overlapping = list.filter(
              (t) => (
                  // classical interval overlap: [start_a, end_a) ∩ [start_b, end_b) ≠ ∅
                  t !== task && t._start < task._end && task._start < t._end
              )
          );
          const lanesSet = /* @__PURE__ */ new Set([task._lane, ...overlapping.map((t) => t._lane)]);
          task._clusterLanes = Math.max(1, lanesSet.size);
        });
        rowMeta.push({
          key: rowKey,
          index: rowIndex,
          lanes: Math.max(1, laneEnds.length),
          height: this.options.row_height
        });
      });
      let cum = 0;
      rowMeta.forEach((r) => {
        r.top = cum;
        cum += r.height;
      });
      this._rows = rows;
      this._rowMeta = rowMeta;
    }
    /**
     * It aggregates overlapping tasks into one special aggregation bar.
     */
    compute_overlap_aggregates() {
      this.tasks.forEach((t) => {
        t._hidden = false;
        t._isAggregate = false;
        t._aggMembers = void 0;
        t._aggregatedBy = void 0;
      });
      this._aggregateBars = [];
      const byEndStartId = (a, b) => {
        if (+a._end !== +b._end) return +a._end - +b._end;
        if (+a._start !== +b._start) return +a._start - +b._start;
        const ia = isFinite(+a.id) ? +a.id : String(a.id);
        const ib = isFinite(+b.id) ? +b.id : String(b.id);
        return ia > ib ? 1 : ia < ib ? -1 : 0;
      };
      const byStartThenId = (a, b) => {
        if (+a._start !== +b._start) return +a._start - +b._start;
        const ia = isFinite(+a.id) ? +a.id : String(a.id);
        const ib = isFinite(+b.id) ? +b.id : String(b.id);
        return ia > ib ? 1 : ia < ib ? -1 : 0;
      };
      const fmt = this.options.date_format || "YYYY-MM-dd";
      const rows = /* @__PURE__ */ new Map();
      this.tasks.forEach((t) => {
        const key = t._rowIndex != null ? t._rowIndex : t._index;
        if (!rows.has(key)) rows.set(key, []);
        rows.get(key).push(t);
      });
      for (const [rowIndex, listRaw] of rows.entries()) {
        if (!listRaw.length) continue;
        const candidates = listRaw.slice().sort(byEndStartId);
        const topLane = [];
        let lastEnd = null;
        for (const t of candidates) {
          if (lastEnd == null || t._start >= lastEnd) {
            topLane.push(t);
            lastEnd = t._end;
          }
        }
        const topSet = new Set(topLane);
        const hidden = listRaw.filter((t) => !topSet.has(t));
        topLane.forEach((t) => {
          t._lane = 0;
          t._rowIndex = rowIndex;
        });
        const rowHasAggregates = hidden.length > 0;
        if (!rowHasAggregates) {
          topLane.forEach((t) => {
            t._clusterLanes = 1;
          });
          continue;
        }
        hidden.sort(byStartThenId);
        const aggs = [];
        let curStart = null, curEnd = null;
        let curMembers = /* @__PURE__ */ new Set();
        const flush = () => {
          if (!curStart) return;
          const membersArr = Array.from(curMembers);
          if (membersArr.length >= 2) {
            let minStart = membersArr[0]._start, maxEnd = membersArr[0]._end;
            for (const m of membersArr) {
              const orig_end = new Date(m.end);
              if (m._start < minStart) minStart = m._start;
              if (orig_end > maxEnd) maxEnd = orig_end;
            }
            const agg = {
              id: `agg_${rowIndex}_${this._aggregateBars.length + aggs.length}`,
              name: `+${membersArr.length}`,
              start: date_utils.format(minStart, fmt),
              //TODO SR: Check if it is needed for the time formating
              end: date_utils.format(maxEnd, fmt),
              /*              end: this.options.step >= 24 && (this.options.step % 24) === 0
                                ? date_utils.format(date_utils.add(maxEnd, -24, 'hour'), fmt)
                                : date_utils.format(date_utils.add(maxEnd, -1, 'second'), fmt),*/
              _start: minStart,
              _end: maxEnd,
              _rowIndex: rowIndex,
              _lane: 1,
              // always at the bottom lane
              _clusterLanes: 2,
              // (Relayout sets real value later)
              lineIndex: membersArr[0].lineIndex,
              draggable: false,
              progress: 0,
              // standard colors for aggregates
              color: "#d2d2ef",
              colorHover: "#c1c1dd",
              progressColor: "#a3a3ff",
              textColor: "#fff",
              custom_class: "aggregate",
              _isAggregate: true,
              _members: membersArr.map((m) => ({
                id: m.id,
                name: m.name,
                _start: m._start,
                _end: m._end,
                end: m.end,
                //TODO SR: Date without hours fix. Test it.
                color: m.color,
                actual_duration: m.actual_duration,
                //TODO SR: It is undefined here because it is only set under "bar.compute_duration()".
                ignored_duration: m.ignored_duration
                //TODO SR: It is undefined here because it is only set under "bar.compute_duration()".
              })),
              _memberNames: membersArr.map((m) => m.name)
            };
            membersArr.forEach((m) => {
              m._hidden = true;
              m._aggregatedBy = agg.id;
            });
            aggs.push(agg);
          } else if (membersArr.length === 1) {
            const single = membersArr[0];
            single._hidden = false;
            single._aggregatedBy = void 0;
            single._lane = 1;
            single._rowIndex = rowIndex;
          }
          curStart = curEnd = null;
          curMembers.clear();
        };
        for (const t of hidden) {
          if (curStart == null) {
            curStart = t._start;
            curEnd = t._end;
            curMembers.add(t);
          } else if (t._start < curEnd) {
            if (t._end > curEnd) curEnd = t._end;
            curMembers.add(t);
          } else {
            flush();
            curStart = t._start;
            curEnd = t._end;
            curMembers.add(t);
          }
        }
        flush();
        this._aggregateBars.push(...aggs);
      }
    }
    /**
     * It re-calculates the visible rows, lanes and cluster sizes after aggregation.
     */
    relayout_visible_rows() {
      const visible = this.tasks.filter((t) => !t._hidden).concat(this._aggregateBars || []);
      const rowMap = /* @__PURE__ */ new Map();
      visible.forEach((t) => {
        const key = t._rowIndex != null ? t._rowIndex : t._index;
        if (!rowMap.has(key)) rowMap.set(key, []);
        rowMap.get(key).push(t);
      });
      const idKey = (t) => Number.isFinite(+t.id) ? +t.id : String(t.id);
      const byStartThenId = (a, b) => {
        const da = +a._start, db = +b._start;
        if (da !== db) return da - db;
        const ia = idKey(a), ib = idKey(b);
        return ia > ib ? 1 : ia < ib ? -1 : 0;
      };
      rowMap.forEach((list, rowIndex) => {
        list.forEach((t) => {
          t._rowIndex = rowIndex;
          t._lane = void 0;
          t._clusterLanes = 1;
        });
        const overlaps = (a, b) => a._start < b._end && b._start < a._end;
        const aggs = list.filter((t) => t._isAggregate === true);
        const topsAll = list.filter((t) => !t._isAggregate).sort(byStartThenId);
        aggs.forEach((a) => {
          a._lane = 1;
          a._clusterLanes = 2;
        });
        const hitAgg = [];
        const noAgg = [];
        topsAll.forEach((t) => (aggs.some((a) => overlaps(t, a)) ? hitAgg : noAgg).push(t));
        hitAgg.forEach((t) => {
          t._lane = 0;
          t._clusterLanes = 2;
        });
        const laneTasks = /* @__PURE__ */ new Map();
        const assignToLane = (task, lane) => {
          task._lane = lane;
          if (!laneTasks.has(lane)) laneTasks.set(lane, []);
          laneTasks.get(lane).push(task);
        };
        aggs.forEach((a) => assignToLane(a, 1));
        hitAgg.forEach((t) => assignToLane(t, 0));
        noAgg.forEach((t) => {
          let lane = 0;
          while (true) {
            const arr = laneTasks.get(lane) || [];
            const collides = arr.some((x) => overlaps(t, x));
            if (!collides) {
              assignToLane(t, lane);
              break;
            }
            lane++;
          }
        });
        const visible2 = list;
        visible2.forEach((t) => {
          const sameRow = visible2.filter((o) => o !== t && overlaps(o, t));
          const laneSet = /* @__PURE__ */ new Set([t._lane, ...sameRow.map((o) => o._lane)]);
          t._clusterLanes = Math.max(1, laneSet.size);
        });
      });
    }
    /**
     * Gets the total content height based on the number of rows and row height
     * @returns {number}
     */
    get_content_height() {
      var _a;
      return (((_a = this._rows) == null ? void 0 : _a.length) || 0) * this.options.row_height;
    }
    /**
     * Binds the outside click to hide popups and unselect tasks.
     */
    bind_outside_click() {
      this._onDocClick = (e) => {
        if (this.bar_being_dragged) return;
        const container = this.$container;
        const target = e.target;
        if (container && container.contains(target)) return;
        this.hide_popup();
        this.unselect_all();
      };
      document.addEventListener("mousedown", this._onDocClick, true);
    }
    /**
     * Calls set_scroll_position according to the "keep_scroll_position" option.
     */
    set_scroll_strategy(scroll_to) {
      if (this._initialScroll || !this.options.keep_scroll_position) {
        this.set_scroll_position(scroll_to);
      }
      if (this._initialScroll) {
        const hasRealTasks = this.tasks.length > 0 && this.tasks[0].name !== "Loading...";
        if (hasRealTasks) {
          this._initialScroll = false;
        }
      } else if (this.options.keep_scroll_position && this.tasks.length === 0) {
        this._initialScroll = true;
      }
    }
    // <<< SR: Bar Aggregation ---------------------------------------------------
  }
  Gantt2.VIEW_MODE = {
    // >>> SR: Bar Aggregation ---------------------------------------------------
    // It currently doesn't work properly with PowerUI
    /*    HOUR: DEFAULT_VIEW_MODES[0],
        QUARTER_DAY: DEFAULT_VIEW_MODES[1],
        HALF_DAY: DEFAULT_VIEW_MODES[2],*/
    // <<< SR: Bar Aggregation ---------------------------------------------------
    DAY: DEFAULT_VIEW_MODES[0],
    WEEK: DEFAULT_VIEW_MODES[1],
    MONTH: DEFAULT_VIEW_MODES[2],
    YEAR: DEFAULT_VIEW_MODES[3]
  };
  function generate_id(task) {
    return task.name + "_" + Math.random().toString(36).slice(2, 12);
  }
  function sanitize(s) {
    return s.replaceAll(" ", "_").replaceAll(":", "_").replaceAll(".", "_");
  }
  return Gantt2;
}();
//# sourceMappingURL=frappe-gantt.js.map