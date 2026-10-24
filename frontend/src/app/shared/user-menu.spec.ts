import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { SignupsService } from '../core/admin/signups.service';
import { Me } from '../core/api/models';
import { AuthService } from '../core/auth/auth.service';
import { UserMenu } from './user-menu';

function render(role: 'leader' | 'member', pending = 0) {
  const me: Me = {
    id: 1,
    username: 'papa',
    displayName: 'David',
    role,
    clan: { id: 1, name: 'Imperium' },
    email: null,
    faction: null,
    canPublishNews: false,
    canAccessGames: false,
    canAccessFilms: false,
    notifications: { gameEventEmails: true },
  };
  TestBed.configureTestingModule({
    imports: [UserMenu],
    providers: [
      provideRouter([]),
      { provide: AuthService, useValue: { user: signal(me), isLeader: () => role === 'leader' } },
      { provide: SignupsService, useValue: { count: signal(pending) } },
    ],
  });
  const fixture = TestBed.createComponent(UserMenu);
  fixture.detectChanges();
  return fixture;
}

describe('UserMenu', () => {
  it('opens a popover with the profile and, for leaders, the administration', () => {
    const el = render('leader', 2).nativeElement as HTMLElement;

    const trigger = el.querySelector('button.trigger')!;
    expect(trigger.getAttribute('popovertarget')).toBe('user-menu');
    expect(el.querySelector('#user-menu')?.getAttribute('popover')).toBe('auto');
    expect(
      [...el.querySelectorAll('#user-menu a')].map((a) => [
        a.textContent?.replace(/\s+/g, ' ').trim(),
        a.getAttribute('href'),
      ]),
    ).toEqual([
      ['Mon profil', '/account'],
      ['Administration 2 demande(s)', '/admin'],
      // In a browser: the Windows application is offered
      ['Application Windows', 'api/desktop/windows/download'],
    ]);
    // Pending sign-ups are signaled on the button too
    expect(trigger.querySelector('.badge')?.textContent).toContain('2');
  });

  it('has no administration link for members', () => {
    const el = render('member').nativeElement as HTMLElement;

    expect([...el.querySelectorAll('#user-menu a')].map((a) => a.getAttribute('href'))).toEqual([
      '/account',
      'api/desktop/windows/download',
    ]);
    expect(el.querySelector('button.trigger .badge')).toBeNull();
  });

  it('logs out from the menu', () => {
    const fixture = render('member');
    let loggedOut = false;
    fixture.componentInstance.logout.subscribe(() => (loggedOut = true));

    [...(fixture.nativeElement as HTMLElement).querySelectorAll('#user-menu button')]
      .find((b) => b.textContent?.includes('Déconnexion'))!
      .dispatchEvent(new Event('click'));

    expect(loggedOut).toBe(true);
  });
});
