;(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory(global.moment, global.exfTools) :
      typeof define === 'function' && define.amd ? define(factory(global.moment, global.exfTools)) :
          global.viewModeBuilder = factory(global.moment, global.exfTools)
}(this, (function (moment, exfTools) { 'use strict';
  
  //TODO SR: "format", "_start_of" and "add" functions should be included from gantt.date_utils
  function _format(date, format_string = 'YYYY-MM-dd HH:mm:ss.SSS') {
    return exfTools.date.format(date, format_string);
  }
  
  function _add(date, qty, scale)
  {
    qty = parseInt(qty, 10);
    return moment(date).add(qty, `${scale}s`).toDate();
  }

  //TODO SR: Copied from date_utils. Test it.
  function _start_of(date, scale) 
  {
    const YEAR = 'year';
    const MONTH = 'month';
    const DAY = 'day';
    const HOUR = 'hour';
    const MINUTE = 'minute';
    const SECOND = 'second';
    const MILLISECOND = 'millisecond';
    
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

    // >>> SR: Bar Aggregation ---------------------------------------------
    if (date === undefined) {
      return new Date();
    }
    // <<< SR: Bar Aggregation ---------------------------------------------

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
  }

  function _getDecade(d) {
    const year = d.getFullYear();
    return String(year - (year % 10));
  }

  function _isBorder(d, ld, interval) {
    if (!ld) return true;

    switch (interval) {
      case 'Date':
        return d.getDate() !== ld.getDate();
      case 'Month':
        return d.getMonth() !== ld.getMonth();
      case 'Year':
        return d.getFullYear() !== ld.getFullYear();
      case 'Decade':
        return _getDecade(d) !== _getDecade(ld);
      default:
        // fallback: treat as always-border
        return true;
    }
  }

  //Thick line quarter calculation fix:
  function _getQuarterStartInInterval(d, step, unit) {
    const intervalStart = _start_of(d, 'day');
    const intervalEnd = _add(intervalStart, step, unit);
    const year = intervalStart.getFullYear();

    for (const month of [0, 3, 6, 9]) {
      const quarterStart = new Date(year, month, 1);

      if (quarterStart >= intervalStart && quarterStart < intervalEnd) {
        return quarterStart;
      }
    }

    const nextYearStart = new Date(year + 1, 0, 1);
    return nextYearStart >= intervalStart && nextYearStart < intervalEnd
        ? nextYearStart
        : false;
  }
  
  function _createHeaderFormatter(def) {
    if (!def) return '';
    const { date_format = '', date_format_at_border = '', interval = null } = def;

    // Token: ~weekRange (start - end of week)
    if (date_format === '~weekRange') {
      return (d, ld, lang) => {
        const endOfWeek = _add(d, 6, 'day');

        const endFormat = endOfWeek.getMonth() !== d.getMonth() ? 'dd MMM' : 'dd';
        const beginFormat = !ld || d.getMonth() !== ld.getMonth() ? 'dd MMM' : 'dd';

        return `${_format(d, beginFormat, lang)} - ${_format(endOfWeek, endFormat, lang)}`;
      };
    }

    // No interval: always use date_format as string
    if (!interval) {
      return date_format || '';
    }

    // Token: ~decade (2020, 2030, ...))
    const formatValue = (d, fmt, lang) => {
      if (!fmt) return '';
      if (fmt === '~decade') return _getDecade(d);
      return _format(d, fmt, lang);
    };

    // If no date_format_at_border is given, date_format is used.
    const borderFmt = date_format_at_border ?? date_format ?? '';
    const normalFmt = date_format ?? '';

    return (d, ld, lang) => {
      const border = _isBorder(d, ld, interval);

      // If no normalFmt is given, only show at border
      if (!normalFmt) {
        return border ? formatValue(d, borderFmt, lang) : '';
      }

      // Standard case
      return border
          ? formatValue(d, borderFmt, lang)
          : formatValue(d, normalFmt, lang);
    };
  }
  
  var viewModeBuilder = {

  /**
   *
   * Builds frappe-gantt view modes from a simple PowerUI friendly configuration object.
   *
   * @param simpleConfig
   * @returns {{name: *, padding: *, step: *, date_format: *, column_width, snap_at, upper_text_frequency, upper_text: string|(function(*, *, *): string)|*|(function(*, *, *): (string|string|*)), lower_text: string|(function(*, *, *): string)|*|(function(*, *, *): (string|string|*)), thick_line: (function(*): (boolean|*))|undefined}[]}
   */
   buildViewModesFromSimpleConfig: function(simpleConfig) {
    return Object.entries(simpleConfig).map(([name, vm]) => {
      const upperDef = vm.header?.upper;
      const lowerDef = vm.header?.lower;

      return {
        name,
        padding: vm.padding,
        step: vm.step,
        date_format: vm.date_format,
        column_width: vm.column_width ?? undefined,
        snap_at: vm.snap_at ?? undefined,
        upper_text_frequency: vm.upper_text_frequency ?? undefined,
        thick_line_color: vm.thick_line_color,

        upper_text: _createHeaderFormatter({
          date_format: upperDef?.date_format ?? '',
          date_format_at_border: upperDef?.date_format_at_border ?? upperDef?.date_format ?? '',
          interval: upperDef?.interval ?? null,
        }),

        lower_text: _createHeaderFormatter({
          date_format: lowerDef?.date_format ?? '',
          date_format_at_border: lowerDef?.date_format_at_border ?? lowerDef?.date_format ?? '',
          interval: lowerDef?.interval ?? null,
        }),

        thick_line: vm.thick_line
            ? (d, ctx = {}) => {
              // Beispiel: Week + Monday
              if (vm.thick_line.interval === 'week') {
                return d.getDay() === vm.thick_line.value;
              }
              if (vm.thick_line.interval === 'month_range_in_days') {
                return d.getDate() >= vm.thick_line.from && d.getDate() <= vm.thick_line.to;
              }
              if (vm.thick_line.interval === 'year_quarter') {
                //return (d.getMonth() % 3 === 0 && d.getDate() >= 1 && d.getDate() <= 7) //TODO SR: Alt
                return _getQuarterStartInInterval(
                    d,
                    ctx.step ?? 1,
                    ctx.unit ?? 'day',
                );
              }
              return false;
            }
            : undefined,
      };
    });
  }
  
  }
  return viewModeBuilder;
})));