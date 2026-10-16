import { chartDay, formatBytes, niceMax, overallState } from './admin-system-format';

describe('system dashboard format', () => {
  it('writes sizes in French units', () => {
    expect(formatBytes(512)).toBe('512 octets');
    expect(formatBytes(22_714)).toBe('22,2 Ko');
    expect(formatBytes(10_116_119)).toBe('9,6 Mo');
    expect(formatBytes(736_339_697_664)).toBe('685,8 Go');
  });

  it('rounds the scale up to a readable value', () => {
    expect([0, 1, 3, 7, 12, 48, 51, 230].map(niceMax)).toEqual([1, 1, 5, 10, 20, 50, 100, 500]);
  });

  it('takes the worst state, switched off services aside', () => {
    const service = (state: 'ok' | 'warning' | 'error' | 'off') => ({
      id: state,
      label: state,
      state,
      detail: '',
    });
    expect(overallState([service('ok'), service('off')])).toEqual({ state: 'ok', problems: 0 });
    expect(overallState([service('warning'), service('ok')])).toEqual({
      state: 'warning',
      problems: 1,
    });
    expect(overallState([service('warning'), service('error')])).toEqual({
      state: 'error',
      problems: 2,
    });
  });

  it('labels the days of the chart', () => {
    expect(chartDay('2026-10-12')).toBe('12/10');
    expect(chartDay('2026-10-12', true)).toBe('lundi 12 octobre');
  });
});
