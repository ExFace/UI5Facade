var Gantt = (function () {
    'use strict';

    const YEAR = 'year';
    const MONTH = 'month';
    const DAY = 'day';
    const HOUR = 'hour';
    const MINUTE = 'minute';
    const SECOND = 'second';
    const MILLISECOND = 'millisecond';

    const month_names = {
        en: [
            '01',
            '02',
            '03',
            '04',
            '05',
            '06',
            '07',
            '08',
            '09',
            '10',
            '11',
            '12',
        ],
        es: [
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ],
        ru: [
            'Январь',
            'Февраль',
            'Март',
            'Апрель',
            'Май',
            'Июнь',
            'Июль',
            'Август',
            'Сентябрь',
            'Октябрь',
            'Ноябрь',
            'Декабрь',
        ],
        ptBr: [
            'Janeiro',
            'Fevereiro',
            'Março',
            'Abril',
            'Maio',
            'Junho',
            'Julho',
            'Agosto',
            'Setembro',
            'Outubro',
            'Novembro',
            'Dezembro',
        ],
        fr: [
            'Janvier',
            'Février',
            'Mars',
            'Avril',
            'Mai',
            'Juin',
            'Juillet',
            'Août',
            'Septembre',
            'Octobre',
            'Novembre',
            'Décembre',
        ],
        tr: [
            'Ocak',
            'Şubat',
            'Mart',
            'Nisan',
            'Mayıs',
            'Haziran',
            'Temmuz',
            'Ağustos',
            'Eylül',
            'Ekim',
            'Kasım',
            'Aralık',
        ],
        zh: [
            '一月',
            '二月',
            '三月',
            '四月',
            '五月',
            '六月',
            '七月',
            '八月',
            '九月',
            '十月',
            '十一月',
            '十二月',
        ],
    };

    var date_utils = {
        parse(date, date_separator = '-', time_separator = /[.:]/) {
            if (date instanceof Date) {
                return date;
            }
            if (typeof date === 'string') {
                let date_parts, time_parts;
                const parts = date.split(' ');

                date_parts = parts[0]
                    .split(date_separator)
                    .map((val) => parseInt(val, 10));
                time_parts = parts[1] && parts[1].split(time_separator);

                // month is 0 indexed
                date_parts[1] = date_parts[1] - 1;

                let vals = date_parts;

                if (time_parts && time_parts.length) {
                    if (time_parts.length == 4) {
                        time_parts[3] = '0.' + time_parts[3];
                        time_parts[3] = parseFloat(time_parts[3]) * 1000;
                    }
                    vals = vals.concat(time_parts);
                }

                return new Date(Date.UTC(...vals)); // 2023-02-10 -> 12445873454...
            }
        },

        to_string(date, with_time = false) {
            if (!(date instanceof Date)) {
                throw new TypeError('Invalid argument type');
            }
            const vals = this.get_date_values(date).map((val, i) => {
                if (i === 1) {
                    // add 1 for month
                    val = val + 1;
                }

                if (i === 6) {
                    return padStart(val + '', 3, '0');
                }

                return padStart(val + '', 2, '0');
            });
            const date_string = `${vals[0]}-${vals[1]}-${vals[2]}`;
            const time_string = `${vals[3]}:${vals[4]}:${vals[5]}.${vals[6]}`;

            return date_string + (with_time ? ' ' + time_string : '');
        },

        format(date, format_string = 'YYYY-MM-DD HH:mm:ss.SSS') {
            return exfTools.date.format(date, format_string);
        },

        diff(date_a, date_b, scale = DAY) {
            let milliseconds, seconds, hours, minutes, days, months, years;

            milliseconds = date_a - date_b;
            seconds = milliseconds / 1000;
            minutes = seconds / 60;
            hours = minutes / 60;
            days = hours / 24;
            months = days / 30;
            years = months / 12;

            if (!scale.endsWith('s')) {
                scale += 's';
            }

            return Math.floor(
                {
                    milliseconds,
                    seconds,
                    minutes,
                    hours,
                    days,
                    months,
                    years,
                }[scale]
            );
        },

        today() {
            const vals = this.get_date_values(new Date()).slice(0, 3);
            return new Date(...vals);
        },

        now() {
            return new Date();
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
                [MILLISECOND]: 0,
            };

            function should_reset(_scale) {
                const max_score = scores[scale];
                return scores[_scale] <= max_score;
            }
            
            if (date === undefined) {
				return new Date();
			}

            const vals = [
                date.getFullYear(),
                should_reset(YEAR) ? 0 : date.getMonth(),
                should_reset(MONTH) ? 1 : date.getDate(),
                should_reset(DAY) ? 0 : date.getHours(),
                should_reset(HOUR) ? 0 : date.getMinutes(),
                should_reset(MINUTE) ? 0 : date.getSeconds(),
                should_reset(SECOND) ? 0 : date.getMilliseconds(),
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
                date.getMilliseconds(),
            ];
        },

        get_days_in_month(date) {
            const no_of_days = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

            const month = date.getMonth();

            if (month !== 1) {
                return no_of_days[month];
            }

            // Feb
            const year = date.getFullYear();
            if ((year % 4 == 0 && year % 100 != 0) || year % 400 == 0) {
                return 29;
            }
            return 28;
        },
    };

    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/padStart
    function padStart(str, targetLength, padString) {
        str = str + '';
        targetLength = targetLength >> 0;
        padString = String(typeof padString !== 'undefined' ? padString : ' ');
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
        return typeof expr === 'string'
            ? (con || document).querySelector(expr)
            : expr || null;
    }

    function createSVG(tag, attrs) {
        const elem = document.createElementNS('http://www.w3.org/2000/svg', tag);
        for (let attr in attrs) {
            if (attr === 'append_to') {
                const parent = attrs.append_to;
                parent.appendChild(elem);
            } else if (attr === 'innerHTML') {
                elem.innerHTML = attrs.innerHTML;
            } else {
                elem.setAttribute(attr, attrs[attr]);
            }
        }
        return elem;
    }

    function animateSVG(svgElement, attr, from, to) {
        const animatedSvgElement = getAnimationElement(svgElement, attr, from, to);

        if (animatedSvgElement === svgElement) {
            // triggered 2nd time programmatically
            // trigger artificial click event
            const event = document.createEvent('HTMLEvents');
            event.initEvent('click', true, true);
            event.eventName = 'click';
            animatedSvgElement.dispatchEvent(event);
        }
    }

    function getAnimationElement(
        svgElement,
        attr,
        from,
        to,
        dur = '0.4s',
        begin = '0.1s'
    ) {
        const animEl = svgElement.querySelector('animate');
        if (animEl) {
            $.attr(animEl, {
                attributeName: attr,
                from,
                to,
                dur,
                begin: 'click + ' + begin, // artificial click
            });
            return svgElement;
        }

        const animateElement = createSVG('animate', {
            attributeName: attr,
            from,
            to,
            dur,
            begin,
            calcMode: 'spline',
            values: from + ';' + to,
            keyTimes: '0; 1',
            keySplines: cubic_bezier('ease-out'),
        });
        svgElement.appendChild(animateElement);

        return svgElement;
    }

    function cubic_bezier(name) {
        return {
            ease: '.25 .1 .25 1',
            linear: '0 0 1 1',
            'ease-in': '.42 0 1 1',
            'ease-out': '0 0 .58 1',
            'ease-in-out': '.42 0 .58 1',
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
        event.split(/\s+/).forEach(function (event) {
            element.addEventListener(event, callback);
        });
    };

    $.delegate = (element, event, selector, callback) => {
        element.addEventListener(event, function (e) {
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
        if (!value && typeof attr === 'string') {
            return element.getAttribute(attr);
        }

        if (typeof attr === 'object') {
            for (let key in attr) {
                $.attr(element, key, attr[key]);
            }
            return;
        }

        element.setAttribute(attr, value);
    };

    class Bar {
        constructor(gantt, task) {
            this.set_defaults(gantt, task);
            this.prepare();
            this.draw();
            this.bind();
        }

        set_defaults(gantt, task) {
            this.action_completed = false;
            this.gantt = gantt;
            this.task = task;
        }

        prepare() {
            this.prepare_values();
            this.prepare_helpers();
        }

        prepare_values() {
            this.invalid = this.task.invalid;
            
            this.height = this.gantt.get_bar_height_for_task(this.task);
          
            this.x = this.compute_x();
            this.y = this.compute_y();

            this.corner_radius = Math.min(this.gantt.options.bar_corner_radius, this.height / 2);
            
            const p = Number.isFinite(+this.task.progress) ? +this.task.progress : 0;
            this.task.progress = Math.min(100, Math.max(0, p));
            
            this.duration =
                date_utils.diff(this.task._end, this.task._start, 'hour') /
                this.gantt.options.step;
            this.width = this.gantt.options.column_width * this.duration;
            this.progress_width =
                this.gantt.options.column_width *
                    this.duration *
                    (this.task.progress / 100) || 0;
            this.group = createSVG('g', {
                class: 'bar-wrapper ' + (this.task.custom_class || ''),
                'data-id': this.task.id,
            });
            this.bar_group = createSVG('g', {
                class: 'bar-group',
                append_to: this.group,
            });
            this.handle_group = createSVG('g', {
                class: 'handle-group',
                append_to: this.group,
            });
        }

        prepare_helpers() {
            SVGElement.prototype.getX = function () {
                return +this.getAttribute('x');
            };
            SVGElement.prototype.getY = function () {
                return +this.getAttribute('y');
            };
            SVGElement.prototype.getWidth = function () {
                return +this.getAttribute('width');
            };
            SVGElement.prototype.getHeight = function () {
                return +this.getAttribute('height');
            };
            SVGElement.prototype.getEndX = function () {
                return this.getX() + this.getWidth();
            };
        }

        draw() {
            this.draw_bar();
            this.draw_progress_bar();
            this.draw_label();
            this.draw_resize_handles();
        }

        draw_bar() {
            this.$bar = createSVG('rect', {
                x: this.x,
                y: this.y,
                width: this.width,
                height: this.height,
                rx: this.corner_radius,
                ry: this.corner_radius,
                class: 'bar',
                append_to: this.bar_group,
            });

            /**
             * +n-block bar buildup
             * 
             * here, the +N-block look is made. 
             * It contains all the bars that overlaps more than 2 times with another bars.
             */
            let defs = this.gantt.$svg.querySelector('defs');
            if (!defs) defs = createSVG('defs', { append_to: this.gantt.$svg });
  
            const inset = 1.5; // px: minimum distance to edge/frame
            const clipId = `clip-legend-${String(this.task.id).replace(/[^a-zA-Z0-9_-]/g, '')}`;

            const oldClip = this.gantt.$svg.querySelector(`#${clipId}`);
            if (oldClip) oldClip.remove();
  
            // ClipPath: ClipPath: rounded rectangle within the bar
            const $cp = createSVG('clipPath', { id: clipId, append_to: defs });
            createSVG('rect', {
              x: this.x + inset,
              y: this.y + inset,
              width: Math.max(0, this.width - inset * 2),
              height: Math.max(0, this.height - inset * 2),
              rx: Math.max(0, this.corner_radius - inset),
              ry: Math.max(0, this.corner_radius - inset),
              append_to: $cp
            });
  
            // +n-block color stripes
            if (this.task._isAggregate && Array.isArray(this.task._members)) {
              const colors = this.task._members.map(m => m && m.color).filter(Boolean);
              const unique = [...new Set(colors)];
              const maxSwatches = 6; // You can increase the max number of showed stripes here
              const swatches = unique.slice(0, maxSwatches);
  
              if (swatches.length) {
                const stripeW = 8;     // The wide of shown stripes in pixel
                const gapX    = 1;     // the gap between the stipes
                const h       = Math.max(0, this.height - inset * 2);
                let xStripe   = this.x + inset; // starting from the left
  
                // Gruppe der Streifen mit Clip
                const stripesGroup = createSVG('g', {
                  append_to: this.bar_group
                });
                stripesGroup.setAttribute('clip-path', `url(#${clipId})`);
  
                swatches.forEach(c => {
                  const r = createSVG('rect', {
                    x: xStripe,
                    y: this.y + inset,
                    width: stripeW,
                    height: h,
                    class: 'agg-stripe-v',
                    append_to: stripesGroup
                  });
                  r.setAttribute('fill', c);
                  r.setAttribute('pointer-events', 'none');
                  xStripe += stripeW + gapX;
                  if (xStripe > this.x + this.width - inset) return; // Safety, if the block is too narrow
                });
              }
            }
            
            // Sets the custom background colour (SVG fill) – overrides default from CSS
            if (this.task.color) {
              this.$bar.style.setProperty('--bar-fill', String(this.task.color));
            }
            if (this.task.colorHover) {
              this.$bar.style.setProperty('--bar-fill-hover', String(this.task.colorHover));
            }
            if (this.task.colorActive) {
              this.$bar.style.setProperty('--bar-fill-active', String(this.task.colorActive));
            }

            animateSVG(this.$bar, 'width', 0, this.width);

            if (this.invalid) {
                this.$bar.classList.add('bar-invalid');
            }
        }

        draw_progress_bar() {
            if (this.invalid) return;
            this.$bar_progress = createSVG('rect', {
                x: this.x,
                y: this.y,
                width: this.progress_width,
                height: this.height,
                rx: this.corner_radius,
                ry: this.corner_radius,
                class: 'bar-progress',
                append_to: this.bar_group,
            });

            // custom progress colors
            if (this.task.progressColor) {
              this.$bar_progress.style.setProperty('--bar-progress-fill', String(this.task.progressColor));
            }
            if (this.task.progressColorHover) {
              this.$bar_progress.style.setProperty('--bar-progress-fill-hover', String(this.task.progressColorHover));
            }
            if (this.task.progressColorActive) {
              this.$bar_progress.style.setProperty('--bar-progress-fill-active', String(this.task.progressColorActive));
            }

            animateSVG(this.$bar_progress, 'width', 0, this.progress_width);
        }

        draw_label() {
          const $label = createSVG('text', {
                x: this.x + this.width / 2,
                y: this.y + this.height / 2,
                innerHTML: this.task.name,
                class: 'bar-label',
                append_to: this.bar_group,
            });
          
            // label color. White is recommended.
            if (this.task.textColor) {
              $label.style.fill = String(this.task.textColor);
            }
            
            // labels get BBox in the next tick
            requestAnimationFrame(() => this.update_label_position());
        }

        draw_resize_handles() {
            if (this.invalid) return;

            const bar = this.$bar;
            const handle_width = 8;

            createSVG('rect', {
                x: bar.getX() + bar.getWidth() - 9,
                y: bar.getY() + 1,
                width: handle_width,
                height: this.height - 2,
                rx: this.corner_radius,
                ry: this.corner_radius,
                class: 'handle right',
                append_to: this.handle_group,
            });

            createSVG('rect', {
                x: bar.getX() + 1,
                y: bar.getY() + 1,
                width: handle_width,
                height: this.height - 2,
                rx: this.corner_radius,
                ry: this.corner_radius,
                class: 'handle left',
                append_to: this.handle_group,
            });

            if (this.task.progress && this.task.progress < 100) {
                this.$handle_progress = createSVG('polygon', {
                    points: this.get_progress_polygon_points().join(','),
                    class: 'handle progress',
                    append_to: this.handle_group,
                });
            }
        }

        get_progress_polygon_points() {
            const bar_progress = this.$bar_progress;
            return [
                bar_progress.getEndX() - 5,
                bar_progress.getY() + bar_progress.getHeight(),
                bar_progress.getEndX() + 5,
                bar_progress.getY() + bar_progress.getHeight(),
                bar_progress.getEndX(),
                bar_progress.getY() + bar_progress.getHeight() - 8.66,
            ];
        }

        bind() {
            if (this.invalid) return;
            this.setup_click_event();
        }

        setup_click_event() {
            $.on(this.group, 'focus ' + this.gantt.options.popup_trigger, (e) => {
                if (this.action_completed) {
                    // just finished a move action, wait for a few seconds
                    return;
                }

                this.show_popup();
                this.gantt.unselect_all();
                this.group.classList.add('active');
            });

            $.on(this.group, 'dblclick', (e) => {
                if (this.action_completed) {
                    // just finished a move action, wait for a few seconds
                    return;
                }

                this.gantt.trigger_event('click', [this.task]);
            });
        }

        show_popup() {
            if (this.gantt.bar_being_dragged) return;

            const start_date = date_utils.format(
                this.task._start,
                this.gantt.options.date_format,
            );

            const adjEnd = (this.gantt.options.step >= 24 && (this.gantt.options.step % 24) === 0)
                ? date_utils.add(this.task._end, -24, 'hour')
                : date_utils.add(this.task._end, -1, 'second');
  
            const end_date = date_utils.format(adjEnd, this.gantt.options.date_format);
          
            const subtitle = start_date + ' - ' + end_date;

            this.gantt.show_popup({
                target_element: this.$bar,
                title: this.task.name,
                subtitle: subtitle,
                task: this.task,
            });
        }

        update_bar_position({ x = null, width = null }) {
            const bar = this.$bar;
            if (x) {
                // get all x values of parent task
                const xs = this.task.dependencies.map((dep) => {
                    return this.gantt.get_bar(dep).$bar.getX();
                });
                // child task must not go before parent
                const valid_x = xs.reduce((prev, curr) => {
                    return x >= curr;
                }, x);
                if (!valid_x) {
                    width = null;
                    return;
                }
                this.update_attr(bar, 'x', x);
            }
            if (width && width >= this.gantt.options.column_width) {
                this.update_attr(bar, 'width', width);
            }
            this.update_label_position();
            this.update_handle_position();
            this.update_progressbar_position();
            this.update_arrow_position();
        }

        date_changed() {
            let changed = false;
            const { new_start_date, new_end_date } = this.compute_start_end_date();

            if (Number(this.task._start) !== Number(new_start_date)) {
                changed = true;
                this.task._start = new_start_date;
            }

            if (Number(this.task._end) !== Number(new_end_date)) {
                changed = true;
                this.task._end = new_end_date;
            }

            if (!changed) return;

            // Remove a bit from the end date (not quite sure why)
	          // If the step is a multiple of 24h, subtract the 24h offset that is added to the end date
	          // Otherwise keep the original solution with -1 second
            this.gantt.trigger_event('date_change', [
              this.task,
              new_start_date,
              this.gantt.options.step >= 24 && (this.gantt.options.step % 24) === 0 ? date_utils.add(new_end_date, -24, 'hour') : date_utils.add(new_end_date, -1, 'second'),
            ]);
        }

        progress_changed() {
            const new_progress = this.compute_progress();
            this.task.progress = new_progress;
            this.gantt.trigger_event('progress_change', [this.task, new_progress]);
        }

        set_action_completed() {
            this.action_completed = true;
            setTimeout(() => (this.action_completed = false), 1000);
        }

        compute_start_end_date(targetBar = null) {
            const bar = targetBar || this.$bar;

            const x_in_units = bar.getX() / this.gantt.options.column_width;
            let new_start_date = date_utils.add(
                this.gantt.gantt_start,
                // Round for cases like 199.999999999995 -> 200
                Math.round(x_in_units * this.gantt.options.step),
                'hour'
            );

            const width_in_units = bar.getWidth() / this.gantt.options.column_width;
            let new_end_date = date_utils.add(
                new_start_date,
                // Round for cases like 199.999999999995 -> 200
                Math.round(width_in_units * this.gantt.options.step),
                'hour'
            );


            return { new_start_date, new_end_date };
        }

        compute_progress() {
            const progress =
                (this.$bar_progress.getWidth() / this.$bar.getWidth()) * 100;
            return parseInt(progress, 10);
        }

        compute_x() {
            const { step, column_width } = this.gantt.options;
            
            const task_start = this.task._start;
            const gantt_start = this.gantt.gantt_start;

            const diff = date_utils.diff(task_start, gantt_start, 'hour');
            let x = (diff / step) * column_width;

            if (this.gantt.view_is('Month')) {
                const diff = date_utils.diff(task_start, gantt_start, 'day');
                x = (diff * column_width) / 30;
            }
            return x;
        }

        compute_y() {
            const rowIndex = (this.task._rowIndex != null) ? this.task._rowIndex : this.task._index;
            const lane = (this.task._lane != null) ? this.task._lane : 0;
    
            const baseY =
                this.gantt.options.header_height +
                this.gantt.options.padding +
                this.gantt.rowTop(rowIndex);
    
            const innerTop = (this.gantt.options.bar_inner_padding || 0) / 2;
    
            // Lane offset remains the same, but starts below the inner top padding
            return baseY + innerTop + lane * (this.height + this.gantt.options.lane_padding);
        }

        get_snap_position(dx) {
            let odx = dx,
                rem,
                position;
  
            if (this.gantt.view_is('Week')) {
              rem = dx % (this.gantt.options.column_width / 7);
              position =
                  odx -
                  rem +
                  (rem < this.gantt.options.column_width / 14
                      ? 0
                      : this.gantt.options.column_width / 7);
            } else if (this.gantt.view_is('Month')) {
              rem = dx % (this.gantt.options.column_width / 30);
              position =
                  odx -
                  rem +
                  (rem < this.gantt.options.column_width / 60
                      ? 0
                      : this.gantt.options.column_width / 30);
              
              //TODO SR INFO: Month Weeks View:
  /*           } else if (this.gantt.view_is('MonthWeeks')) {
               rem = dx % (this.gantt.options.column_width / 30);
               position = odx - rem + (rem < this.gantt.options.column_width / 60 ? 0 : this.gantt.options.column_width / 30);*/
            } else {
              rem = dx % this.gantt.options.column_width;
              position =
                  odx -
                  rem +
                  (rem < this.gantt.options.column_width / 2
                      ? 0
                      : this.gantt.options.column_width);
            }
            return position;
        }

        update_attr(element, attr, value) {
            value = +value;
            if (!isNaN(value)) {
                element.setAttribute(attr, value);
            }
            return element;
        }

        update_progressbar_position() {
          if (!this.$bar_progress) return;
          
          const bw = this.$bar.getWidth();
          const raw = Number.isFinite(+this.task.progress) ? +this.task.progress : 0;
          const progress = Math.min(100, Math.max(0, raw)); //0–100 clamp
  
          this.$bar_progress.setAttribute('x', this.$bar.getX());
          this.$bar_progress.setAttribute('width', bw * (progress / 100));
        }

        // This is a part of the label_overflow logic
        update_label_position() {
          const bar = this.$bar;
          const label = this.group.querySelector('.bar-label');
          if (!label) return;
  
          const overflow = this.gantt.options.label_overflow || 'outside';
          const padding = 6;
          const fits = label.getBBox().width <= Math.max(0, bar.getWidth() - padding);
  
          // Basis: Always align labels centrally
          label.classList.remove('big');
          label.classList.remove('clip-left');
          label.setAttribute('text-anchor', 'middle');
          label.setAttribute('x', bar.getX() + bar.getWidth() / 2);
          label.setAttribute('y', bar.getY() + bar.getHeight() / 2);
  
          if (fits) {
            // shows the label if it fits inside the bar
            label.style.display = '';
            label.removeAttribute('clip-path');
            return;
          }
  
          // If the label doesn't fit inside the bar:
          if (overflow === 'outside') {
            // The label is displayed on the right outside the bar.
            label.classList.add('big');
            label.style.display = '';
            label.setAttribute('text-anchor', 'start');
            label.setAttribute('x', bar.getX() + bar.getWidth() + 5);
            label.removeAttribute('clip-path');
          } else if (overflow === 'hide') {
            label.style.display = 'none';
            label.removeAttribute('clip-path');
          } else if (overflow === 'clip') {
            // The label is clipped inside the bar. 
            // It is useful if multiple bars are at the same line index, 
            // so that the labels do not overlap. 
            label.style.display = '';
            label.classList.remove('big');
            const inset = 2;
            
            label.classList.add('clip-left');
            label.classList.remove('big');
            label.setAttribute('x', bar.getX() + inset);
            label.setAttribute('y', bar.getY() + bar.getHeight() / 2);

            // ClipPath: cuts ONLY on the right (and top/bottom), not on the left
            const clipId = `clip-label-${String(this.task.id).replace(/[^a-zA-Z0-9_-]/g,'')}`;
            let defs = this.gantt.$svg.querySelector('defs');
            if (!defs) defs = createSVG('defs', { append_to: this.gantt.$svg });

            // Remove old clip if necessary to avoid duplicates.
            const old = this.gantt.$svg.querySelector(`#${clipId}`);
            if (old) old.remove();

            const cp = createSVG('clipPath', { id: clipId, append_to: defs });
            createSVG('rect', {
              x: bar.getX() + inset,
              y: bar.getY() + inset,
              width: Math.max(0, bar.getWidth() - inset * 2),
              height: Math.max(0, bar.getHeight() - inset * 2),
              rx: Math.max(0, this.corner_radius - inset),
              ry: Math.max(0, this.corner_radius - inset),
              append_to: cp
            });

            label.setAttribute('clip-path', `url(#${clipId})`);
          }
        }

        update_handle_position() {
            const bar = this.$bar;
            this.handle_group
                .querySelector('.handle.left')
                .setAttribute('x', bar.getX() + 1);
            this.handle_group
                .querySelector('.handle.right')
                .setAttribute('x', bar.getEndX() - 9);
            const handle = this.group.querySelector('.handle.progress');
            handle &&
                handle.setAttribute('points', this.get_progress_polygon_points());
        }

        update_arrow_position() {
            this.arrows = this.arrows || [];
            for (let arrow of this.arrows) {
                arrow.update();
            }
        }
    }

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

        const condition = () =>
            this.to_task.$bar.getX() < start_x + this.gantt.options.padding &&
            start_x > this.from_task.$bar.getX() + this.gantt.options.padding;

        while (condition()) start_x -= 10;
        
        const start_y = this.from_task.$bar.getY() + this.from_task.$bar.getHeight() / 2;
        const end_x   = this.to_task.$bar.getX() - this.gantt.options.padding / 2;
        const end_y   = this.to_task.$bar.getY() + this.to_task.$bar.getHeight() / 2;

        const from_is_below_to = start_y > end_y;
        const curve = this.gantt.options.arrow_curve;
        const clockwise = from_is_below_to ? 1 : 0;
        const curve_y = from_is_below_to ? -curve : curve;
        const offset = from_is_below_to ? end_y + curve : end_y - curve;

        this.path = `
          M ${start_x} ${start_y}
          V ${offset}
          a ${curve} ${curve} 0 0 ${clockwise} ${curve} ${curve_y}
          L ${end_x} ${end_y}
          m -5 -5
          l 5 5
          l -5 5`;

        if (this.to_task.$bar.getX() <
            this.from_task.$bar.getX() + this.gantt.options.padding) {
          const down_1 = this.gantt.options.padding / 2 - curve;
          const down_2 = end_y - curve_y;
          const left   = this.to_task.$bar.getX() - this.gantt.options.padding;

          this.path = `
              M ${start_x} ${start_y}
              v ${down_1}
              a ${curve} ${curve} 0 0 1 -${curve} ${curve}
              H ${left}
              a ${curve} ${curve} 0 0 ${clockwise} -${curve} ${curve_y}
              V ${down_2}
              a ${curve} ${curve} 0 0 ${clockwise} ${curve} ${curve_y}
              L ${end_x} ${end_y}
              m -5 -5
              l 5 5
              l -5 5`;
        }
      }

      draw() {
            this.element = createSVG('path', {
                d: this.path,
                'data-from': this.from_task.task.id,
                'data-to': this.to_task.task.id,
            });
        }

        update() {
            this.calculate_path();
            this.element.setAttribute('d', this.path);
        }
    }

    class Popup {
        constructor(parent, custom_html, gantt) {
            this.parent = parent;
            this.gantt = gantt;
            this.custom_html = custom_html;
            this.make();
        }

        make() {
            this.parent.innerHTML = `
            <div class="title"></div>
            <div class="subtitle"></div>
            <div class="pointer"></div>
        `;

            this.hide();

            this.title = this.parent.querySelector('.title');
            this.subtitle = this.parent.querySelector('.subtitle');
            this.pointer = this.parent.querySelector('.pointer');
        }

        show(options) {
            if (!options?.target_element) {
                throw new Error('target_element is required to show popup');
            }
            if (!options.position) {
                options.position = 'left';
            }
            const target_element = options.target_element;

            if (this.custom_html) {
                let html = this.custom_html(options.task);
                html += '<div class="pointer"></div>';
                this.parent.innerHTML = html;
                this.pointer = this.parent.querySelector('.pointer');
            } else {
                // set data
                this.title.innerHTML = options.title;
                this.subtitle.innerHTML = options.subtitle;
                this.parent.style.width = this.parent.clientWidth + 'px';

                // aggregates the data of overlapping bars for the +n-block
                const t = options.task;
                const members = t._isAggregate ? (t._members || []) : (t._aggMembers || []);
  
                // Remove existing old list (when reopening)
                const old = this.parent.querySelector('.agg-list');
                if (old) old.remove();
  
                if (members && members.length) {
                  const ul = document.createElement('ul');
                  ul.className = 'agg-list';
                  const fmt = (d) => this.gantt.dateUtils.format(d, this.gantt.options.date_format || 'YYYY-MM-DD');

                  // gleiche Logik wie bei normalen Bars:
                  const adjustEnd = (d) => {
                    const step = this.gantt.options.step;
                    const du = this.gantt.dateUtils;
                    return (step >= 24 && (step % 24) === 0)
                        ? du.add(d, -24, 'hour')     // Tages-/Wochen-/Monats-Skalierung: -24h
                        : du.add(d, -1, 'second');   // Feiner als Tag: -1s
                  };

                  members.forEach(m => {
                    const li = document.createElement('li');
                    if (m._start && m._end) {
                      li.textContent = `${m.name} (${fmt(m._start)} – ${fmt(adjustEnd(m._end))})`;
                    } else {
                      li.textContent = m.name;
                    }
                    ul.appendChild(li);
                  });
                  
                  this.parent.appendChild(ul);
                }
            }

            // set position
            let position_meta;
            if (target_element instanceof HTMLElement) {
                position_meta = target_element.getBoundingClientRect();
            } else if (target_element instanceof SVGElement) {
                position_meta = options.target_element.getBBox();
            }

            if (options.position === 'left') {
                this.parent.style.left =
                    position_meta.x + (position_meta.width + 10) + 'px';
                this.parent.style.top = position_meta.y + 'px';

                this.pointer.style.transform = 'rotateZ(90deg)';
                this.pointer.style.left = '-7px';
                this.pointer.style.top = '2px';
            }

            // show
            this.parent.style.opacity = 1;
        }

        hide() {
            this.parent.style.opacity = 0;
            this.parent.style.left = 0;
        }

        move(options = {}) {
            const { bar, target_element } = options;

            const { new_start_date, new_end_date } = bar.compute_start_end_date(target_element)

            const start_date = date_utils.format(
                new_start_date,
                this.gantt.options.date_format,
            );

            const adjEnd = (this.gantt.options.step >= 24 && (this.gantt.options.step % 24) === 0)
                ? date_utils.add(new_end_date, -24, 'hour')
                : date_utils.add(new_end_date, -1, 'second');
  
            const end_date = date_utils.format(adjEnd, this.gantt.options.date_format);

            this.title.innerHTML = bar?.task?.name;
            this.subtitle.innerHTML = start_date + ' - ' + end_date;
            this.parent.style.width = this.parent.clientWidth + 'px';

            if (!options?.target_element) {
                throw new Error('target_element is required to move popup');
            }
            if (!options.position) {
                options.position = 'left';
            }

            const { width: wrapper_width} = this.parent.getBoundingClientRect();

            // set position
            let position_meta;
            if (target_element instanceof HTMLElement) {
                position_meta = target_element.getBoundingClientRect();
            } else if (target_element instanceof SVGElement) {
                position_meta = options.target_element.getBBox();
            }

            if (options.position === 'left') {
                this.parent.style.left =
                    position_meta.x + (position_meta.width + 10) + 'px';
                this.parent.style.top = position_meta.y + 'px';

                this.pointer.style.transform = 'rotateZ(90deg)';
                this.pointer.style.left = '-7px';
                this.pointer.style.top = '2px';
            } else if (options.position === 'right') {
                this.parent.style.left =
                    position_meta.x + (-1 * (wrapper_width + 7)) + 'px';
                this.parent.style.top = position_meta.y + 'px';

                this.pointer.style.transform = 'rotateZ(-90deg)';
                this.pointer.style.left = wrapper_width + 7 + 'px';
                this.pointer.style.top = '2px';
            }

            this.parent.style.opacity = 1;
        }
    }

    const VIEW_MODE = {
        QUARTER_DAY: 'Quarter Day',
        HALF_DAY: 'Half Day',
        DAY: 'Day',
        WEEK: 'Week',
        MONTH: 'Month',
        YEAR: 'Year',
       // MONTH_WEEKS: 'MonthWeeks', //TODO SR INFO: Month Weeks View:
    };

    //TODO SR INFO: Month Weeks View (figure out how to set the start of the week day and adjust this function):
  
    /*function start_of_week_sunday(d) {
      // 0=So,1=Mo,... also "wie viele Tage seit Sonntag" zurückspringen
      const day = d.getDay(); // 0..6
      return date_utils.add(date_utils.start_of(d, 'day'), -day, 'day');
    }*/

    class Gantt {
        constructor(wrapper, tasks, options) {
            this.setup_wrapper(wrapper);
            this.setup_options(options);
            this.setup_tasks(tasks);
            // initialize with default view mode
            this.change_view_mode();
            this.bind_events();
        }

        setup_wrapper(element) {
            let svg_element, wrapper_element;

            // CSS Selector is passed
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }

            // get the SVGElement
            if (element instanceof HTMLElement) {
                wrapper_element = element;
                svg_element = element.querySelector('svg');
            } else if (element instanceof SVGElement) {
                svg_element = element;
            } else {
                throw new TypeError(
                    'Frappé Gantt only supports usage of a string CSS selector,' +
                        " HTML DOM element or SVG DOM element for the 'element' parameter"
                );
            }

            // svg element
            if (!svg_element) {
                // create it
                this.$svg = createSVG('svg', {
                    append_to: wrapper_element,
                    class: 'gantt',
                });
            } else {
                this.$svg = svg_element;
                this.$svg.classList.add('gantt');
            }

            // wrapper element
            this.$container = document.createElement('div');
            this.$container.classList.add('gantt-container');

            const parent_element = this.$svg.parentElement;
            parent_element.appendChild(this.$container);
            this.$container.appendChild(this.$svg);

            // initial scroll 
            this._initialScrollDone = false;

            // popup wrapper
            this.popup_wrapper = document.createElement('div');
            this.popup_wrapper.classList.add('popup-wrapper');
            this.$container.appendChild(this.popup_wrapper);
        }

        setup_options(options) {
            const default_options = {
                header_height: 50,
                column_width: 30,
                step: 24,
                view_modes: [...Object.values(VIEW_MODE)],
                bar_height: 20,
                bar_corner_radius: 3,
                arrow_curve: 5,
                padding: 18,
                view_mode: 'Day',
                date_format: 'YYYY-MM-dd',
                popup_trigger: 'click',
                custom_popup_html: null,
                language: 'en',
                label_overflow: 'outside', // 'outside' | 'hide' | 'clip'
                keep_scroll_position: false,
                lane_padding: 4, // vertical distance between lanes in the same row
                // Automatically rearrange when dragging/resizing. 
                // Necessary if multiple bars are at the same lineIndex:
                auto_relayout_on_change: false, 
                row_height: null, //is calculated automatically, if set to null
                bar_inner_padding: 6, // Total vertical padding within the row for each task
                default_duration: 1,
                view_mode_column_width_day: 38,
                view_mode_column_width_week: 140,
                view_mode_column_width_month: 20,
                view_mode_column_width_year: 12
            };
            
            this.options = Object.assign({}, default_options, (options || {}));
            
            if (this.options.row_height == null) {
              this.options.row_height = this.options.bar_height + this.options.padding;
            }
  
            if (this.options.bar_inner_padding == null) {
              this.options.bar_inner_padding = 6;
            }
        }

        setup_tasks(tasks) {
            // prepare tasks
            this.tasks = tasks.map((task, i) => {
              
              const hadStartObj = !!task._start;
              const hadEndObj = !!task._end;
              
              // convert to Date objects
              task._start = hadStartObj ? task._start : date_utils.parse(task.start);
              task._end = hadEndObj ? task._end : date_utils.parse(task.end);

                // make task invalid if duration too large
                if (date_utils.diff(task._end, task._start, 'year') > 10) {
                    task.end = null;
                }

                // cache index
                task._index = i;

                // invalid dates
                if (!task.start && !task.end) {
                    const today = date_utils.today();
                    task._start = today;
                  //TODO SR: The "-1" makes the task-block to disappear, but it breaks the overlapping logik a little bit
                    task._end = date_utils.add(today, this.options.default_duration, 'day');
                }

                if (!task.start && task.end) {
                    task._start = date_utils.add(task._end, - this.options.default_duration, 'day');
                }

                if (task.start && !task.end) {
                    task._end = date_utils.add(task._start, this.options.default_duration, 'day');
                }

              
                // only add +24 hours to the FIRST parse (not with every refresh)
                // This is necessary if the start and end dates are on the same day, 
                // to ensure that the bar can still be displayed.

                // if hours is not set, assume the last day is full day
                // e.g: 2018-09-09 becomes 2018-09-09 23:59:59
                if (this.options.step >= 24 && (this.options.step % 24) === 0) { //TODO SR: Check, why here is a ">=" in the condition.
                  task._end = date_utils.add(task._end, 24, 'hour');
                }
            
                // invalid flag
                if (!task.start || !task.end) {
                    task.invalid = true;
                }

                // dependencies
                if (typeof task.dependencies === 'string' || !task.dependencies) {
                    let deps = [];
                    if (task.dependencies) {
                        deps = task.dependencies
                            .split(',')
                            .map((d) => d.trim())
                            .filter((d) => d);
                    }
                    task.dependencies = deps;
                }

                // uids
                if (!task.id) {
                    task.id = generate_id(task);
                }

                return task;
            });

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

        change_view_mode(mode = this.options.view_mode) {
            const changed = mode !== this.options.view_mode;
            this.update_view_scale(mode);
            this.setup_dates();
            if (changed) this._initialScrollDone = false;
            this.render();
            // fire viewmode_change event
            this.trigger_event('view_change', [mode]);
        }

        update_view_scale(view_mode) {
            this.options.view_mode = view_mode;

            if (view_mode === VIEW_MODE.DAY) {
                this.options.step = 24;
                this.options.column_width = this.options.view_mode_column_width_day;
            } else if (view_mode === VIEW_MODE.HALF_DAY) {
                this.options.step = 24 / 2;
                this.options.column_width = this.options.view_mode_column_width_day;
            } else if (view_mode === VIEW_MODE.QUARTER_DAY) {
                this.options.step = 24 / 4;
                this.options.column_width = this.options.view_mode_column_width_day;
            } else if (view_mode === VIEW_MODE.WEEK) {
                this.options.step = 24 * 7;
                this.options.column_width = this.options.view_mode_column_width_week;
            } else if (view_mode === VIEW_MODE.MONTH) {
                this.options.step = 24 * 30;
                this.options.column_width = this.options.view_mode_column_width_month;
            } else if (view_mode === VIEW_MODE.YEAR) {
                this.options.step = 24 * 365;
                this.options.column_width = this.options.view_mode_column_width_year;
            } /*else if (view_mode === VIEW_MODE.MONTH_WEEKS) {  //TODO SR INFO: Month Weeks View:
              this.options.step = 24 * 7;
              this.options.column_width = 38;
            }*/
        }

        setup_dates() {
            this.setup_gantt_dates();
            this.setup_date_values();
        }

        setup_gantt_dates() {
            this.gantt_start = this.gantt_end = null;

            for (let task of this.tasks) {
                // set global start and end date
                if (!this.gantt_start || task._start < this.gantt_start) {
                    this.gantt_start = task._start;
                }
                if (!this.gantt_end || task._end > this.gantt_end) {
                    this.gantt_end = task._end;
                }
            }

            this.gantt_start = date_utils.start_of(this.gantt_start, 'day');
            this.gantt_end = date_utils.start_of(this.gantt_end, 'day');

            // add date padding on both sides
            if (this.view_is([VIEW_MODE.QUARTER_DAY, VIEW_MODE.HALF_DAY])) {
                this.gantt_start = date_utils.add(this.gantt_start, -7, 'day');
                this.gantt_end = date_utils.add(this.gantt_end, 7, 'day');
            } else if (this.view_is(VIEW_MODE.MONTH)) {
                this.gantt_start = date_utils.start_of(this.gantt_start, 'year');
                this.gantt_end = date_utils.add(this.gantt_end, 1, 'year');
            } else if (this.view_is(VIEW_MODE.YEAR)) {
                this.gantt_start = date_utils.add(this.gantt_start, -2, 'year');
                this.gantt_end = date_utils.add(this.gantt_end, 2, 'year');
           /* } else if (this.view_is(VIEW_MODE.MONTH_WEEKS)) { //TODO SR INFO: Month Weeks View:
              const s = start_of_week_sunday(date_utils.add(this.gantt_start, -7, 'day'));
              const e = start_of_week_sunday(date_utils.add(this.gantt_end,   14, 'day'));
              this.gantt_start = s;
              this.gantt_end = date_utils.add(e, 7, 'day');*/
            } else {
                this.gantt_start = date_utils.add(this.gantt_start, -1, 'month');
                this.gantt_end = date_utils.add(this.gantt_end, 1, 'month');
            }

            this.gantt_start = date_utils.add(this.gantt_start, -1 * this.gantt_start.getTimezoneOffset(), 'minute');
        }

        setup_date_values() {
            this.dates = [];
            let cur_date = null;

            while (cur_date === null || cur_date < this.gantt_end) {
                if (!cur_date) {
                    cur_date = date_utils.clone(this.gantt_start);
/*                  if (this.view_is(VIEW_MODE.MONTH_WEEKS)) { //TODO SR INFO: Month Weeks View:
                    cur_date = start_of_week_sunday(date_utils.clone(this.gantt_start));
                  }*/
                } else {
                    if (this.view_is(VIEW_MODE.YEAR)) {
                        cur_date = date_utils.add(cur_date, 1, 'year');
                    } else if (this.view_is(VIEW_MODE.MONTH)) {
                        cur_date = date_utils.add(cur_date, 1, 'month');
/*                    } else if (this.view_is(VIEW_MODE.MONTH_WEEKS)) { //TODO SR INFO: Month Weeks View:
                      cur_date = date_utils.add(cur_date, 7, 'day');*/

                    } else {
                        cur_date = date_utils.add(
                            cur_date,
                            this.options.step,
                            'hour'
                        );
                    }
                }
                this.dates.push(cur_date);
            }
        }

        bind_events() {
            this.bind_grid_click();
            this.bind_bar_events();
        }

        render() {
          // container merken (Eltern-Element des SVG)
          const container = this.$svg ? this.$svg.parentElement : null;
          const prevScrollLeft = container ? container.scrollLeft : null;
          
            this.clear();
            this.setup_layers();
            this.make_grid();
            this.make_dates();
            this.make_bars();
            this.make_arrows();
            this.map_arrows_on_bars();
            this.set_width();

            // Scroll strategy:
            // 1) For the very first render: always centre on the first tasks
            // 2) Then: if keep_scroll_position: Keep scroll position exactly
            if (!this._initialScrollDone) {
              this.set_scroll_position();
              this._initialScrollDone = true;
            } else if (this.options.keep_scroll_position && container) {
              container.scrollLeft = prevScrollLeft;
            } else {
              this.set_scroll_position();
            }
        }

        setup_layers() {
            this.layers = {};
            const layers = ['grid', 'date', 'arrow', 'progress', 'bar', 'details'];
            // make group layers
            for (let layer of layers) {
                this.layers[layer] = createSVG('g', {
                    class: layer,
                    append_to: this.$svg,
                });
            }
        }

        make_grid() {
            this.make_grid_background();
            this.make_grid_rows();
            this.make_grid_header();
            this.make_grid_ticks();
            this.make_grid_highlights();
        }

        make_grid_background() {
          const grid_width = this.dates.length * this.options.column_width;
          const grid_height =
              this.options.header_height +
              this.options.padding +
              this.get_content_height();
  
          createSVG('rect', {
            x: 0, y: 0,
            width: grid_width,
            height: grid_height,
            class: 'grid-background',
            append_to: this.layers.grid,
          });
  
          $.attr(this.$svg, {
            height: grid_height + this.options.padding + 100,
            width: '100%',
          });
        }

        make_grid_rows() {
          const rows_layer  = createSVG('g', { append_to: this.layers.grid });
          const lines_layer = createSVG('g', { append_to: this.layers.grid });
  
          const row_width  = this.dates.length * this.options.column_width;
  
          this._rowMeta.forEach(r => {
            const row_y = this.options.header_height + this.options.padding + r.top;
            const row_h = this.options.row_height;
  
            createSVG('rect', {
              x: 0, y: row_y,
              width: row_width, height: row_h,
              class: 'grid-row',
              append_to: rows_layer,
            });
  
            createSVG('line', {
              x1: 0, y1: row_y + row_h,
              x2: row_width, y2: row_y + row_h,
              class: 'row-line',
              append_to: lines_layer,
            });
          });
        }

        make_grid_header() {
            const header_width = this.dates.length * this.options.column_width;
            const header_height = this.options.header_height + 10;
            createSVG('rect', {
                x: 0,
                y: 0,
                width: header_width,
                height: header_height,
                class: 'grid-header',
                append_to: this.layers.grid,
            });
        }
        
        make_grid_ticks() {
            let tick_x = 0;
            let tick_y = this.options.header_height + this.options.padding / 2;

            let tick_height = this.get_content_height();

            //TODO SR INFO: Month Weeks View:
/*          
            const header_box_height = this.options.header_height + 10;

            const full_height = header_box_height + this.options.padding / 2 + tick_height;

              for (let i= 0; i<this.dates.length; i++) {
                const date = this.dates[i];*/

            for (let date of this.dates) {
                let tick_class = 'tick';
                // thick tick for monday
                if (this.view_is(VIEW_MODE.DAY) && date.getDate() === 1) {
                    tick_class += ' thick';
                }
                // thick tick for first week
                if (
                    this.view_is(VIEW_MODE.WEEK) &&
                    date.getDate() >= 1 &&
                    date.getDate() < 8
                ) {
                    tick_class += ' thick';
                }
                // thick ticks for quarters
                if (
                    this.view_is(VIEW_MODE.MONTH) &&
                    (date.getMonth() + 1) % 3 === 0
                ) {
                    tick_class += ' thick';
                }

                  //TODO SR INFO: Month Weeks View:

/*                if (this.view_is(VIEW_MODE.MONTH_WEEKS)) {
                  createSVG('path', {
                    d: `M ${tick_x} ${tick_y} v ${tick_height}`,
                    class: 'tick',
                    append_to: this.layers.grid,
                  });

                  const weekStart = date; // dies ist bereits Sonntag
                  const weekEnd   = date_utils.add(weekStart, 7, 'day');

                  const monthStart    = new Date(weekStart.getFullYear(), weekStart.getMonth(), 1);
                  const monthBoundary = date_utils.add(monthStart, 1, 'month'); // 1. des Folgemonats

                  if (monthBoundary > weekStart && monthBoundary <= weekEnd) {
                    const msWeek = weekEnd - weekStart;
                    const msToBoundary = monthBoundary - weekStart;
                    const ratio = msToBoundary / msWeek; // 0..1 innerhalb der Spalte
                    const xBoundary = tick_x + ratio * this.options.column_width;

                    createSVG('path', {
                      d: `M ${xBoundary} 0 v ${full_height}`,
                      class: 'tick boundary month-end',
                      append_to: this.layers.grid,
                    });
                  }

                  tick_x += this.options.column_width;
                  continue;
                }*/

                createSVG('path', {
                    d: `M ${tick_x} ${tick_y} v ${tick_height}`,
                    class: tick_class,
                    append_to: this.layers.grid,
                });

                if (this.view_is(VIEW_MODE.MONTH)) {
                    tick_x +=
                        (date_utils.get_days_in_month(date) *
                            this.options.column_width) /
                        30;
                } else {
                    tick_x += this.options.column_width;
                }
            }
        }

        make_grid_highlights() {
          // highlight today's date
          if (this.view_is(VIEW_MODE.DAY)) {
            const x = (date_utils.diff(date_utils.today(), this.gantt_start, 'hour') / this.options.step) * this.options.column_width;
            const y = 0;
            const width = this.options.column_width;
            const height =
                this.get_content_height() +
                this.options.header_height +
                this.options.padding / 2;
  
            createSVG('rect', {
              x, y, width, height,
              class: 'today-highlight',
              append_to: this.layers.grid,
            });
          }
        }

        make_dates() {
          for (let date of this.get_dates_to_draw()) {
            createSVG('text', {
              x: date.lower_x,
              y: date.lower_y,
              innerHTML: date.lower_text,
              class: 'lower-text',
              append_to: this.layers.date,
            });
  
            if (date.upper_text) {
              const $upper_text = createSVG('text', {
                x: date.upper_x,
                y: date.upper_y,
                innerHTML: date.upper_text,
                class: 'upper-text',
                append_to: this.layers.date,
              });
  
              // remove out-of-bound dates
              if (
                  $upper_text.getBBox().x2 > this.layers.grid.getBBox().width
              ) {
                $upper_text.remove();
              }
            }
          }
        }

      //TODO SR INFO: Month Weeks View:

     /* make_dates() {
        const isMonthWeeks = this.view_is && this.view_is('MonthWeeks');

        this.layers.date.innerHTML = '';

        // draw once, cache
        const draw = this.get_dates_to_draw();

        for (let i = 0; i < draw.length; i++) {
          const d = draw[i];

          createSVG('text', {
            x: d.lower_x,
            y: d.lower_y,
            innerHTML: d.lower_text,
            class: 'lower-text',
            append_to: this.layers.date,
          });

          if (!isMonthWeeks && d.upper_text) {
            const $upper_text = createSVG('text', {
              x: d.upper_x,
              y: d.upper_y,
              innerHTML: d.upper_text,
              class: 'upper-text',
              append_to: this.layers.date,
            });
            // out-of-bounds entfernen
            if ($upper_text.getBBox().x2 > this.layers.grid.getBBox().width) {
              $upper_text.remove();
            }
          }
        }

        if (isMonthWeeks) {
          this.draw_monthweeks_upper_labels();
        }
      }

      draw_monthweeks_upper_labels() {
        const cw = this.options.column_width;
        const upperY = this.options.header_height - 25;

        const localMidnight = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const addDaysLocal  = (d, days) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + days);

        const xForDate = (dt) => {
          const weekMs = 7 * 24 * 60 * 60 * 1000;
          // finde Woche k, so dass dt in [dates[k], dates[k]+7)
          let k = -1;
          for (let i = 0; i < this.dates.length; i++) {
            const ws = localMidnight(this.dates[i]);
            const we = addDaysLocal(ws, 7);
            if (dt >= ws && dt < we) { k = i; break; }
          }
          if (k < 0) {
            // außerhalb → clamp an Rand
            if (dt < this.dates[0]) return 0;
            return this.dates.length * cw;
          }
          const ws = localMidnight(this.dates[k]);
          const ratio = Math.max(0, Math.min(1, (dt - ws) / (7 * 24 * 60 * 60 * 1000)));
          return k * cw + ratio * cw;
        };

        const firstVisible = localMidnight(this.dates[0]);
        const lastVisible  = localMidnight(this.dates[this.dates.length - 1]);

        let cursor = new Date(firstVisible.getFullYear(), firstVisible.getMonth(), 1);

        if (firstVisible > cursor) {
        }

        while (cursor <= lastVisible) {
          const monthStart = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
          const nextMonthStart = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1);

          if (nextMonthStart > this.dates[0] && monthStart < addDaysLocal(localMidnight(this.dates[this.dates.length - 1]), 7)) {
            const xStart = xForDate(monthStart);
            const xEnd   = xForDate(nextMonthStart);
            const xMid   = (xStart + xEnd) / 2;

            const $t = createSVG('text', {
              x: xMid,
              y: upperY,
              innerHTML: date_utils.format(monthStart, 'MMM YYYY'),
              class: 'upper-text',
              append_to: this.layers.date,
            });

            if ($t.getBBox().x2 > this.layers.grid.getBBox().width) {
              $t.remove();
            }
          }

          cursor = nextMonthStart;
        }
      }
*/

        get_dates_to_draw() {
              let last_date = null;
              const dates = this.dates.map((date, i) => {
                  const d = this.get_date_info(date, last_date, i);
                  last_date = date;
                  return d;
              });
              return dates;
        }

        get_date_info(date, last_date, i) {
            if (!last_date) {
                last_date = date_utils.add(date, 1, 'year');
            }
            
            const date_text = {
                'Quarter Day_lower': date_utils.format(
                    date,
                    'HH',
                ),
                'Half Day_lower': date_utils.format(
                    date,
                    'HH',
                ),
                Day_lower:
                    date.getDate() !== last_date.getDate()
                        ? date_utils.format(date, 'd')
                        : '',
                //Day_lower: dayChanged ? String(date.getDate()) : '',
                Week_lower:
                    date.getMonth() !== last_date.getMonth()
                        ? date_utils.format(date, 'd MMM')
                        : date_utils.format(date, 'd'),
                Month_lower: date_utils.format(date, 'M'),
                Year_lower: date_utils.format(date, 'YYYY'),
                'Quarter Day_upper':
                    date.getDate() !== last_date.getDate()
                        ? date_utils.format(date, 'd MMM')
                        : '',
                'Half Day_upper':
                    date.getDate() !== last_date.getDate()
                        ? date.getMonth() !== last_date.getMonth()
                            ? date_utils.format(
                                  date,
                                  'd MMM',
                              )
                            : date_utils.format(date, 'd')
                        : '',
                Day_upper:
                    date.getMonth() !== last_date.getMonth()
                        ? date_utils.format(date, 'MMM')
                        : '',
                Week_upper:
                    date.getMonth() !== last_date.getMonth()
                        ? date_utils.format(date, 'M')
                        : '',
                Month_upper:
                    date.getFullYear() !== last_date.getFullYear()
                        ? date_utils.format(date, 'YYYY')
                        : '',
                Year_upper:
                    date.getFullYear() !== last_date.getFullYear()
                        ? date_utils.format(date, 'YYYY')
                        : '',
              
              //TODO SR INFO: Month Weeks View:
                /*
                //MonthWeeks_lower: date_utils.format(date, 'd MMM'),
                MonthWeeks_lower: date_utils.format(date, 'd'),
                MonthWeeks_upper:
                    (!last_date || date.getMonth() !== last_date.getMonth())
                        ? date_utils.format(date, 'MMM YYYY')
                        : '',
                */
            };

            const base_pos = {
                x: i * this.options.column_width,
                lower_y: this.options.header_height,
                upper_y: this.options.header_height - 25,
            };

            const x_pos = {
                'Quarter Day_lower': (this.options.column_width * 4) / 2,
                'Quarter Day_upper': 0,
                'Half Day_lower': (this.options.column_width * 2) / 2,
                'Half Day_upper': 0,
                Day_lower: this.options.column_width / 2,
                Day_upper: (this.options.column_width * 30) / 2,
                Week_lower: 0,
                Week_upper: (this.options.column_width * 4) / 2,
                Month_lower: this.options.column_width / 2,
                Month_upper: (this.options.column_width * 12) / 2,
                Year_lower: this.options.column_width / 2,
                Year_upper: (this.options.column_width * 30) / 2,
              
                //TODO SR INFO: Month Weeks View:
/*              MonthWeeks_lower: this.options.column_width / 2, 
                MonthWeeks_upper: (this.options.column_width * 4) / 2, */
            };

            return {
                upper_text: date_text[`${this.options.view_mode}_upper`],
                lower_text: date_text[`${this.options.view_mode}_lower`],
                upper_x: base_pos.x + x_pos[`${this.options.view_mode}_upper`],
                upper_y: base_pos.upper_y,
                lower_x: base_pos.x + x_pos[`${this.options.view_mode}_lower`],
                lower_y: base_pos.lower_y,
            };
        }

        make_bars() {
          // Only render non-hidden tasks + all aggregates
          const renderTasks = this.tasks.filter(t => !t._hidden)
          .concat(this._aggregateBars || []);
          
          // Draw the lower lanes first, then the upper ones (lane 0 last).
          renderTasks.sort((a, b) => {
            const ra = (a._rowIndex ?? a._index) - (b._rowIndex ?? b._index);
            if (ra !== 0) return ra;
            // Draw the larger lane first so that the smaller ones (above) lie on top of it.
            const la = (a._lane ?? 0), lb = (b._lane ?? 0);
            if (la !== lb) return lb - la;
            // stabile Tie-Breaker
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

        // Quick access: taskId -> Bar (rendered bars only)
        const barById = new Map();
        for (const bar of this.bars) {
          if (bar && bar.task && bar.task.id != null) {
            barById.set(bar.task.id, bar);
          }
        }

        for (const task of this.tasks) {
          if (!task || !Array.isArray(task.dependencies) || !task.dependencies.length) continue;

          // Target bar must be visible
          const toBar = barById.get(task.id);
          if (!toBar) continue;

          for (const depId of task.dependencies) {
            const depTask = this.get_task(depId);
            if (!depTask) continue; // ungültige ID

            // Source bar (dependency) must be visible
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
                    return (
                        arrow.from_task.task.id === bar.task.id ||
                        arrow.to_task.task.id === bar.task.id
                    );
                });
            }
        }

        set_width() {
            const cur_width = this.$svg.getBoundingClientRect().width;
            const actual_width = this.$svg
                .querySelector('.grid .grid-row')
                .getAttribute('width');
            if (cur_width < actual_width) {
                this.$svg.setAttribute('width', actual_width);
            }
        }

        set_scroll_position() {
            const parent_element = this.$svg.parentElement;
          if (!parent || !this.tasks || !this.tasks.length) return;

            const hours_before_first_task = date_utils.diff(
                this.get_oldest_starting_date(),
                this.gantt_start,
                'hour'
            );

            const scroll_pos =
                (hours_before_first_task / this.options.step) *
                    this.options.column_width -
                this.options.column_width;

            parent_element.scrollLeft = scroll_pos;
        }

        bind_grid_click() {
            $.on(
                this.$svg,
                this.options.popup_trigger,
                '.grid-row, .grid-header',
                () => {
                    this.unselect_all();
                    this.hide_popup();
                }
            );
        }

        bind_bar_events() {
            let is_dragging = false;
            let x_on_start = 0;
            let y_on_start = 0;
            let is_resizing_left = false;
            let is_resizing_right = false;
            let parent_bar_id = null;
            let bars = []; // instanceof Bar
            this.bar_being_dragged = null;

            function action_in_progress() {
                return is_dragging || is_resizing_left || is_resizing_right;
            }

            $.on(this.$svg, 'mousedown', '.bar-wrapper, .handle', (e, element) => {
                const bar_wrapper = $.closest('.bar-wrapper', element);

                if (element.classList.contains('left')) {
                    is_resizing_left = true;
                } else if (element.classList.contains('right')) {
                    is_resizing_right = true;
                } else if (element.classList.contains('bar-wrapper')) {
                    is_dragging = true;
                }

                bar_wrapper.classList.add('active');

                x_on_start = e.offsetX;
                y_on_start = e.offsetY;

                parent_bar_id = bar_wrapper.getAttribute('data-id');
                const ids = [
                    parent_bar_id,
                    ...this.get_all_dependent_tasks(parent_bar_id),
                ];
                bars = ids.map((id) => this.get_bar(id));

                this.bar_being_dragged = parent_bar_id;

                bars.forEach((bar) => {
                    const $bar = bar.$bar;
                    $bar.ox = $bar.getX();
                    $bar.oy = $bar.getY();
                    $bar.owidth = $bar.getWidth();
                    $bar.finaldx = 0;
                });
            });

            $.on(this.$svg, 'mousemove', (e) => {
                if (!action_in_progress()) return;
                const dx = e.offsetX - x_on_start;
                var bDraggable = true;
                bars.forEach((bar) => {
                    if (bar.task.draggable === false) {
						          bDraggable = false;
					          }
				        });
				        if (bDraggable === false) {
				        	e.preventDefault(); 
				        	e.stopPropagation(); 
				        	return false;
				        }

                bars.forEach((bar) => {
                    const $bar = bar.$bar;
                    $bar.finaldx = this.get_snap_position(dx);
                    
                    if (is_resizing_left) {
                        this.move_popup({ target_element: $bar, bar });
                        if (parent_bar_id === bar.task.id) {
                            bar.update_bar_position({
                                x: $bar.ox + $bar.finaldx,
                                width: $bar.owidth - $bar.finaldx,
                            });
                        } else {
                            bar.update_bar_position({
                                x: $bar.ox + $bar.finaldx,
                            });
                        }
                    } else if (is_resizing_right) {
                        this.move_popup({ target_element: $bar, position: 'right', bar });
                        if (parent_bar_id === bar.task.id) {
                            bar.update_bar_position({
                                width: $bar.owidth + $bar.finaldx,
                            });
                        }
                    } else if (is_dragging) {
                        bar.update_bar_position({ x: $bar.ox + $bar.finaldx });
                        this.move_popup({ target_element: $bar, bar });
                    }
                });
            });

            document.addEventListener('mouseup', (e) => {
                if (is_dragging || is_resizing_left || is_resizing_right) {
                    bars.forEach((bar) => bar.group.classList.remove('active'));
                }

                is_dragging = false;
                is_resizing_left = false;
                is_resizing_right = false;
            });
            
            $.on(this.$svg, 'mouseup', (e) => {
              // only react if an action was actually performed
              if (!(is_dragging || is_resizing_left || is_resizing_right)) {
                return;
              }
  
              this.bar_being_dragged = null;
  
              bars.forEach((bar) => {
                const $bar = bar.$bar;
                if (!$bar.finaldx) return;
                
                bar.date_changed();
                bar.set_action_completed();
  
                // Resets delta, otherwise each subsequent click will trigger it again
                $bar.finaldx = 0;
              });
            });

            this.bind_bar_progress();
        }

        bind_bar_progress() {
            let x_on_start = 0;
            let y_on_start = 0;
            let is_resizing = null;
            let bar = null;
            let $bar_progress = null;
            let $bar = null;

            $.on(this.$svg, 'mousedown', '.handle.progress', (e, handle) => {
                is_resizing = true;
                x_on_start = e.offsetX;
                y_on_start = e.offsetY;

                const $bar_wrapper = $.closest('.bar-wrapper', handle);
                const id = $bar_wrapper.getAttribute('data-id');
                bar = this.get_bar(id);

                $bar_progress = bar.$bar_progress;
                $bar = bar.$bar;

                $bar_progress.finaldx = 0;
                $bar_progress.owidth = $bar_progress.getWidth();
                $bar_progress.min_dx = -$bar_progress.getWidth();
                $bar_progress.max_dx = $bar.getWidth() - $bar_progress.getWidth();
            });

            $.on(this.$svg, 'mousemove', (e) => {
                if (!is_resizing) return;
                let dx = e.offsetX - x_on_start;
                e.offsetY - y_on_start;

                if (dx > $bar_progress.max_dx) {
                    dx = $bar_progress.max_dx;
                }
                if (dx < $bar_progress.min_dx) {
                    dx = $bar_progress.min_dx;
                }

                const $handle = bar.$handle_progress;
                $.attr($bar_progress, 'width', $bar_progress.owidth + dx);
                $.attr($handle, 'points', bar.get_progress_polygon_points());
                $bar_progress.finaldx = dx;
            });

            $.on(this.$svg, 'mouseup', () => {
                is_resizing = false;
                if (!($bar_progress && $bar_progress.finaldx)) return;
                bar.progress_changed();
                bar.set_action_completed();
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

        get_snap_position(dx) {
            let odx = dx,
                rem,
                position;

            if (this.view_is(VIEW_MODE.WEEK)) {
                rem = dx % (this.options.column_width / 7);
                position =
                    odx -
                    rem +
                    (rem < this.options.column_width / 14
                        ? 0
                        : this.options.column_width / 7);
            } else if (this.view_is(VIEW_MODE.MONTH)) {
                rem = dx % (this.options.column_width / 30);
                position =
                    odx -
                    rem +
                    (rem < this.options.column_width / 60
                        ? 0
                        : this.options.column_width / 30);

              //TODO SR INFO: Month Weeks View:
              
/*            } else if (this.view_is('MonthWeeks')) {
              rem = dx % (this.options.column_width / 7);
              position = odx - rem + (rem < this.options.column_width / 14 ? 0 : this.options.column_width / 7);*/
            } else {
                rem = dx % this.options.column_width;
                position =
                    odx -
                    rem +
                    (rem < this.options.column_width / 2
                        ? 0
                        : this.options.column_width);
            }
            return position;
        }

        unselect_all() {
            [...this.$svg.querySelectorAll('.bar-wrapper')].forEach((el) => {
                el.classList.remove('active');
            });
        }

        view_is(modes) {
            if (typeof modes === 'string') {
                return this.options.view_mode === modes;
            }

            if (Array.isArray(modes)) {
                return modes.some((mode) => this.options.view_mode === mode);
            }

            return false;
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

        show_popup(options) {
            if (!this.popup) {
                this.popup = new Popup(
                    this.popup_wrapper,
                    this.options.custom_popup_html,
                    this
                );
            }
            this.popup.show(options);
        }

        hide_popup() {
            this.popup && this.popup.hide();
        }

        move_popup(options) {
            if (!this.popup) {
                this.popup = new Popup(
                    this.popup_wrapper,
                    this.options.custom_popup_html,
                    this
                );
            }
            this.popup.move(options);
        }

        trigger_event(event, args) {
            if (this.options['on_' + event]) {
                this.options['on_' + event].apply(null, args);
            }
        }

        /**
         * Gets the oldest starting date from the list of tasks
         *
         * @returns Date
         * @memberof Gantt
         */
        get_oldest_starting_date() {
            return this.tasks
                .map((task) => task._start)
                .reduce((prev_date, cur_date) =>
                    cur_date <= prev_date ? cur_date : prev_date
                );
        }

        /**
         * Clear all elements from the parent svg element
         *
         * @memberof Gantt
         */
        clear() {
            this.$svg.innerHTML = '';
        }

        /* 
         * Exporting date_utils to outside of Gantt module
         * 
         */
        dateUtils = date_utils;
        
        compute_rows_and_lanes() {
          // 1) Row key per task (lineIndex preferred)
          this.tasks.forEach(t => {
            t._rowKey = (t.lineIndex !== undefined) ? t.lineIndex : t._index;
          });
  
          // 2) Group by row
          const rowMap = new Map();
          this.tasks.forEach(t => {
            if (!rowMap.has(t._rowKey)) rowMap.set(t._rowKey, []);
            rowMap.get(t._rowKey).push(t);
          });
  
          // 3) Sorting row list
          const rows = Array.from(rowMap.keys()).sort((a,b) => (a>b?1:a<b?-1:0));
  
          // 4) Lane allocation per row (greedy)
          const rowMeta = [];
          rows.forEach((rowKey, rowIndex) => {
            const list = rowMap.get(rowKey).slice().sort((a,b) => +a._start - +b._start);
            const laneEnds = []; // laneIndex -> Date
  
            list.forEach(task => {
              let lane = 0;
              while (lane < laneEnds.length && !(laneEnds[lane] <= task._start)) lane++;
              task._lane = lane;
              task._rowIndex = rowIndex;
              laneEnds[lane] = task._end;
            });
            
            // calculates the overlap cluster size per task
            list.forEach(task => {
              // All tasks in the same row that overlap with another task bar:
              const overlapping = list.filter(t =>
                  // classical interval overlap: [start_a, end_a) ∩ [start_b, end_b) ≠ ∅
                  (t !== task) && (t._start < task._end) && (task._start < t._end)
              );
              // Number of lanes occupied during THIS time slot:
              const lanesSet = new Set([task._lane, ...overlapping.map(t => t._lane)]);
              task._clusterLanes = Math.max(1, lanesSet.size);
            });
  
            rowMeta.push({
              key: rowKey,
              index: rowIndex,
              lanes: Math.max(1, laneEnds.length),
              height: this.options.row_height,
            });
          });
  
          // 5) Top offsets with fixed row height
          let cum = 0;
          rowMeta.forEach(r => {
            r.top = cum;
            cum += r.height; // fix for each row
          });
  
          this._rows = rows;
          this._rowMeta = rowMeta;
        }

        // Row top & content height (fixed)
        rowTop(rowIndex) {
          return this._rowMeta[rowIndex]?.top || 0;
        }
        get_content_height() {
          // Height of the content zone = rows * row_height
          return (this._rows?.length || 0) * this.options.row_height;
        }
        
      
        get_bar_height_for_task(task) {
          const lanes = Math.max(1, task._clusterLanes || 1);
          const inner = Math.max(0, this.options.bar_inner_padding || 0);
          const laneGaps = (lanes - 1) * this.options.lane_padding;
  
          // available height = row height minus inner padding minus gaps between lanes
          const available = this.options.row_height - inner - laneGaps;
  
          const h = available / lanes;
          return Math.max(6, h); // small lower limit so that handles/labels remain usable
        }

      /**
       * Here, overlapping tasks are aggregated into an +n-block.
       */
        compute_overlap_aggregates() {
            // Reset
            this.tasks.forEach(t => {
              t._hidden = false;
              t._isAggregate = false;
              t._aggMembers = undefined;
              t._aggregatedBy = undefined;
            });
            this._aggregateBars = [];
    
            const byEndStartId = (a,b) => {
              // Greedy für Top-Lane: sort by end, then start, then id
              if (+a._end !== +b._end) return +a._end - +b._end;
              if (+a._start !== +b._start) return +a._start - +b._start;
              const ia = isFinite(+a.id) ? +a.id : String(a.id);
              const ib = isFinite(+b.id) ? +b.id : String(b.id);
              return ia > ib ? 1 : ia < ib ? -1 : 0;
            };
            const byStartThenId = (a,b) => {
              if (+a._start !== +b._start) return +a._start - +b._start;
              const ia = isFinite(+a.id) ? +a.id : String(a.id);
              const ib = isFinite(+b.id) ? +b.id : String(b.id);
              return ia > ib ? 1 : ia < ib ? -1 : 0;
            };
            const fmt = this.options.date_format || 'YYYY-MM-DD';
    
            // group rows
            const rows = new Map();
            this.tasks.forEach(t => {
              const key = (t._rowIndex != null) ? t._rowIndex : t._index;
              if (!rows.has(key)) rows.set(key, []);
              rows.get(key).push(t);
            });
    
            for (const [rowIndex, listRaw] of rows.entries()) {
              if (!listRaw.length) continue;
    
              // 1) top lane via interval scheduling (max. non-overlapping tasks)
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
              const hidden = listRaw.filter(t => !topSet.has(t)); // everything that is not at the top
    
              // Set lanes
              topLane.forEach(t => { t._lane = 0; t._rowIndex = rowIndex; });
              const rowHasAggregates = hidden.length > 0;
    
              if (!rowHasAggregates) {
                // 1. lane if not hidden
                topLane.forEach(t => { t._clusterLanes = 1; });
                continue;
              }
    
              // 2) Summarise hidden: sort with start and form union
              hidden.sort(byStartThenId);
    
              const aggs = [];
              let curStart = null, curEnd = null;
              let curMembers = new Set();
    
              const bottomSingles = []; // collect visible individual tasks
    
              const flush = () => {
                if (!curStart) return;
    
                const membersArr = Array.from(curMembers);
                if (membersArr.length >= 2) {
                  // === Aggregat bauen (wie bisher) ===
                  let minStart = membersArr[0]._start, maxEnd = membersArr[0]._end;
                  for (const m of membersArr) {
                    if (m._start < minStart) minStart = m._start;
                    if (m._end   > maxEnd)   maxEnd   = m._end;
                  }
    
                  const agg = {
                    id: `agg_${rowIndex}_${this._aggregateBars.length + aggs.length}`,
                    name: `+${membersArr.length}`,
                    start: this.dateUtils.format(minStart, fmt),
                    end:   this.options.step >= 24 && (this.options.step % 24) === 0
                        ? this.dateUtils.format(this.dateUtils.add(maxEnd, -24, 'hour'), fmt)
                        : this.dateUtils.format(this.dateUtils.add(maxEnd, -1, 'second'), fmt),
    
                    _start: minStart,
                    _end:   maxEnd,
                    _rowIndex: rowIndex,
                    _lane: 1,                 // always at the bottom lane
                    _clusterLanes: 2,         // (Relayout sets real value later)
                    lineIndex: membersArr[0].lineIndex,
    
                    draggable: false,
                    progress: 0,
                    
                    //TODO: The standart colours of the +n-block are given here. They can be set via UXON later.
                    color: '#d2d2ef',
                    colorHover: '#c1c1dd',
                    colorActive: '#c1c1dd',
                    progressColor: '#a3a3ff',
                    textColor: '#fff',
    
                    custom_class: 'aggregate',
                    _isAggregate: true,
    
                    _members: membersArr.map(m => ({
                      id: m.id, name: m.name, _start: m._start, _end: m._end, color: m.color
                    })),
                    _memberNames: membersArr.map(m => m.name),
                  };
    
                  // hide members
                  membersArr.forEach(m => { m._hidden = true; m._aggregatedBy = agg.id; });
                  aggs.push(agg);
    
                } else if (membersArr.length === 1) {
                  // No aggregation, if the bottom lane have only one task
                  const single = membersArr[0];
                  single._hidden = false;
                  single._aggregatedBy = undefined;
                  single._lane = 1;
                  single._rowIndex = rowIndex;
                  bottomSingles.push(single);
                }
    
                curStart = curEnd = null;
                curMembers.clear();
              };
    
              for (const t of hidden) {
                if (curStart == null) {
                  curStart = t._start;
                  curEnd   = t._end;
                  curMembers.add(t);
                } else if (t._start < curEnd) {
                  // overlaps -> in this union segment
                  if (t._end > curEnd) curEnd = t._end;
                  curMembers.add(t);
                } else {
                  // Gap -> flush old segment and start a new one
                  flush();
                  curStart = t._start;
                  curEnd = t._end;
                  curMembers.add(t);
                }
              }
              flush();
    
              // Take over aggregate bars
              this._aggregateBars.push(...aggs);
            }
        }
      
        relayout_visible_rows() {
          const visible = this.tasks.filter(t => !t._hidden)
          .concat(this._aggregateBars || []);
  
          const overlaps = (a,b) => (a._start < b._end) && (b._start < a._end);
  
          const rowMap = new Map();
          visible.forEach(t => {
            const key = (t._rowIndex != null) ? t._rowIndex : t._index;
            if (!rowMap.has(key)) rowMap.set(key, []);
            rowMap.get(key).push(t);
          });
  
          const idKey = (t) => (Number.isFinite(+t.id) ? +t.id : String(t.id));
          const byStartThenId = (a,b) => {
            const da = +a._start, db = +b._start;
            if (da !== db) return da - db;
            const ia = idKey(a), ib = idKey(b);
            return ia > ib ? 1 : ia < ib ? -1 : 0;
          };
          
          rowMap.forEach((list, rowIndex) => {
            // hard resets for each row
            list.forEach(t => {
              t._rowIndex = rowIndex;
              t._lane = undefined;
              t._clusterLanes = 1; // Default
            });

            const overlaps = (a,b) => (a._start < b._end) && (b._start < a._end);

            const aggs = list.filter(t => t._isAggregate === true);
            const topsAll = list.filter(t => !t._isAggregate).sort(byStartThenId);

            // 1) Aggregates always on lane 1, cluster=2 (they have a partner ‘above’)
            aggs.forEach(a => {
              a._lane = 1;
              a._clusterLanes = 2;
            });

            // Top-Tasks:
            // 2) Tasks that intersect an aggregate in time -> Lane 0, cluster=2 (partner of the aggregate)
            const hitAgg = [];
            const noAgg  = [];
            topsAll.forEach(t => (aggs.some(a => overlaps(t,a)) ? hitAgg : noAgg).push(t));

            hitAgg.forEach(t => {
              t._lane = 0;
              t._clusterLanes = 2;
            });

            // 3) Collect allocations per lane (by time)
            const laneTasks = new Map(); // lane -> Array<Task>
            const assignToLane = (task, lane) => {
              task._lane = lane;
              if (!laneTasks.has(lane)) laneTasks.set(lane, []);
              laneTasks.get(lane).push(task);
            };

            // Seed: already assigned (aggregate + hitAgg)
            aggs.forEach(a => assignToLane(a, 1));
            hitAgg.forEach(t => assignToLane(t, 0));

            // 4) Place noAgg in the first collision-free lane, sorted by start
            noAgg.forEach(t => {
              let lane = 0;
              while (true) {
                const arr = laneTasks.get(lane) || [];
                const collides = arr.some(x => overlaps(t, x));
                if (!collides) {
                  assignToLane(t, lane);
                  break;
                }
                lane++;
              }
            });

            // 5) edefine cluster lanes (visible only)
            const visible = list;
            visible.forEach(t => {
              const sameRow = visible.filter(o => o !== t && overlaps(o, t));
              const laneSet = new Set([t._lane, ...sameRow.map(o => o._lane)]);
              t._clusterLanes = Math.max(1, laneSet.size);
            });
          });
        }
    }

    Gantt.VIEW_MODE = VIEW_MODE;

    function generate_id(task) {
        return task.name + '_' + Math.random().toString(36).slice(2, 12);
    }

    return Gantt;

})();
//# sourceMappingURL=frappe-gantt.js.map