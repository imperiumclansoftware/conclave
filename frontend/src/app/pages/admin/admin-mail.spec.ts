import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { AdminService } from '../../core/admin/admin.service';
import { MailSettings, MailSettingsInput } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { AdminMail } from './admin-mail';

const SAVED: MailSettings = {
  configured: true,
  source: 'admin',
  envServer: false,
  server: {
    mode: 'smtp',
    host: 'ssl0.ovh.net',
    port: 465,
    security: 'tls',
    username: 'clan@exemple.fr',
    passwordSet: true,
    verifyPeer: true,
    customDsn: null,
  },
  from: { value: 'clan@exemple.fr', source: 'admin' },
  fromName: { value: null, source: null },
};

describe('AdminMail', () => {
  let saved: MailSettingsInput | null;
  let tested: { mail: MailSettingsInput; recipient: string | null } | null;

  beforeEach(() => {
    saved = tested = null;
    TestBed.configureTestingModule({
      providers: [
        {
          provide: AdminService,
          useValue: {
            saveMail: (mail: MailSettingsInput) => ((saved = mail), of({ mail: SAVED })),
            testMail: (mail: MailSettingsInput, recipient: string | null) => (
              (tested = { mail, recipient }),
              of({ ok: true, checks: [] })
            ),
          },
        },
        {
          provide: AuthService,
          useValue: { user: signal({ email: 'chef@exemple.fr', clan: { name: 'Imperium' } }) },
        },
      ],
    });
  });

  function render() {
    const fixture = TestBed.createComponent(AdminMail);
    fixture.componentRef.setInput('mail', SAVED);
    fixture.detectChanges();
    const el = fixture.nativeElement as HTMLElement;
    const field = <T extends HTMLElement>(id: string) => el.querySelector<T>('#' + id)!;
    const change = (id: string, value: string) => {
      const input = field<HTMLInputElement | HTMLSelectElement>(id);
      input.value = value;
      input.dispatchEvent(new Event(input instanceof HTMLSelectElement ? 'change' : 'input'));
      fixture.detectChanges();
    };
    return { fixture, el, field, change };
  }

  it('shows the saved server, recognises the provider and keeps the password', async () => {
    const { el, field, fixture } = render();

    expect(field<HTMLSelectElement>('mail-provider').value).toBe('ovh');
    expect(field<HTMLInputElement>('mail-port').value).toBe('465');
    expect(field<HTMLInputElement>('mail-password').placeholder).toContain('Enregistré');
    expect(field<HTMLInputElement>('mail-recipient').value).toBe('chef@exemple.fr');

    el.querySelector<HTMLButtonElement>('button[type=submit]')!.click();
    await fixture.whenStable();

    // An empty password field keeps the saved password
    expect(saved).toEqual({
      mode: 'smtp',
      host: 'ssl0.ovh.net',
      port: 465,
      security: 'tls',
      username: 'clan@exemple.fr',
      password: null,
      verifyPeer: true,
      dsn: null,
      from: 'clan@exemple.fr',
      fromName: null,
    });
  });

  it('fills in a provider, follows the encryption with the port and tests unsaved values', async () => {
    const { el, field, change, fixture } = render();

    change('mail-provider', 'gmail');
    expect([
      field<HTMLInputElement>('mail-host').value,
      field<HTMLInputElement>('mail-port').value,
      field<HTMLSelectElement>('mail-security').value,
    ]).toEqual(['smtp.gmail.com', '587', 'starttls']);
    expect(el.textContent).toContain('mot de passe d’application');
    // Another server: the saved password is not sent there
    expect(el.textContent).toContain("n'est pas réutilisé");

    change('mail-security', 'tls');
    expect(field<HTMLInputElement>('mail-port').value).toBe('465');

    change('mail-host', 'smtp.exemple.fr');
    expect(field<HTMLSelectElement>('mail-provider').value).toBe('');

    change('mail-recipient', 'ami@exemple.fr');
    [...el.querySelectorAll<HTMLButtonElement>('button')]
      .find((b) => b.textContent?.includes('e-mail de test'))!
      .click();
    await fixture.whenStable();

    expect(tested?.recipient).toBe('ami@exemple.fr');
    expect(tested?.mail).toMatchObject({ host: 'smtp.exemple.fr', port: 465, security: 'tls' });
    expect(saved).toBeNull();
  });
});
