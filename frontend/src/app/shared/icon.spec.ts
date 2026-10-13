import { TestBed } from '@angular/core/testing';
import { Icon } from './icon';
import { ICONS } from './icons.generated';

describe('Icon', () => {
  it('draws the icon in the current color, hidden from assistive technologies by default', () => {
    const fixture = TestBed.createComponent(Icon);
    fixture.componentRef.setInput('name', 'messages');
    fixture.detectChanges();
    const svg = (fixture.nativeElement as HTMLElement).querySelector('svg')!;

    expect(svg.getAttribute('viewBox')).toBe('0 0 512 512');
    expect(svg.getAttribute('aria-hidden')).toBe('true');
    expect(svg.querySelectorAll('path')).toHaveLength(ICONS.messages.paths.length);
    expect(svg.querySelector('path')!.getAttribute('fill')).toBe('currentColor');
  });

  it('is announced as an image when labelled', () => {
    const fixture = TestBed.createComponent(Icon);
    fixture.componentRef.setInput('name', 'discord');
    fixture.componentRef.setInput('label', 'Discord');
    fixture.detectChanges();
    const svg = (fixture.nativeElement as HTMLElement).querySelector('svg')!;

    expect([
      svg.getAttribute('role'),
      svg.getAttribute('aria-label'),
      svg.getAttribute('aria-hidden'),
    ]).toEqual(['img', 'Discord', null]);
    expect(svg.getAttribute('viewBox')).toBe('0 0 24 24');
  });
});
