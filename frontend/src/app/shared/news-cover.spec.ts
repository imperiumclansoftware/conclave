import { TestBed } from '@angular/core/testing';
import { NewsCover } from './news-cover';

describe('NewsCover', () => {
  function render(seed: string, title = seed) {
    const fixture = TestBed.createComponent(NewsCover);
    fixture.componentRef.setInput('seed', seed);
    fixture.componentRef.setInput('title', title);
    fixture.componentRef.setInput('clanName', 'Imperium');
    fixture.detectChanges();
    return fixture.nativeElement as HTMLElement;
  }

  it('draws both factions, with the initial of the title and the clan name', () => {
    const el = render('éclipse sur Valhalla');

    expect(el.querySelectorAll('g.only-assassin, g.only-templar').length).toBe(2);
    expect(el.querySelector('text')?.textContent?.trim()).toBe('É');
    expect(el.textContent).toContain('IMPERIUM');
    expect(el.querySelector('svg')?.getAttribute('aria-label')).toBe(
      'Illustration : éclipse sur Valhalla',
    );
  });

  it('keeps the same visual for the same title and changes it for another', () => {
    const rays = (el: HTMLElement) =>
      [...el.querySelectorAll('g.only-assassin path')].map((p) => p.getAttribute('d')).join();

    expect(rays(render('Soirée Valhalla'))).toBe(rays(render('Soirée Valhalla')));
    expect(rays(render('Soirée Valhalla'))).not.toBe(rays(render('Tournoi Rocket League')));
  });
});
