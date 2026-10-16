import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import {
  AdminMember,
  AdminSettings,
  CheckResult,
  ClanRole,
  MailSettingsInput,
  SystemReport,
} from '../api/models';

export interface DiscordSettingsChanges {
  clientId: string;
  redirectUri: string;
  /** null: unchanged (secrets are never sent back by the API) */
  clientSecret: string | null;
  botToken: string | null;
}

@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);

  system(): Observable<SystemReport> {
    return this.http.get<SystemReport>('api/admin/system');
  }

  settings(): Observable<AdminSettings> {
    return this.http.get<AdminSettings>('api/admin/settings');
  }

  saveDiscord(changes: DiscordSettingsChanges): Observable<AdminSettings> {
    return this.http.put<AdminSettings>('api/admin/settings/discord', changes);
  }

  saveRawg(apiKey: string): Observable<AdminSettings> {
    return this.http.put<AdminSettings>('api/admin/settings/rawg', { apiKey });
  }

  testDiscord(): Observable<CheckResult> {
    return this.http.post<CheckResult>('api/admin/discord/test', null);
  }

  testRawg(): Observable<CheckResult> {
    return this.http.post<CheckResult>('api/admin/rawg/test', null);
  }

  /** apiKey: "" removes the saved key */
  saveTmdb(apiKey: string): Observable<AdminSettings> {
    return this.http.put<AdminSettings>('api/admin/settings/tmdb', { apiKey });
  }

  testTmdb(): Observable<CheckResult> {
    return this.http.post<CheckResult>('api/admin/tmdb/test', null);
  }

  saveGeneral(clanName: string, appUrl: string): Observable<AdminSettings> {
    return this.http.put<AdminSettings>('api/admin/settings/general', {
      clanName,
      appUrl: appUrl || null,
    });
  }

  saveMail(mail: MailSettingsInput): Observable<AdminSettings> {
    return this.http.put<AdminSettings>('api/admin/settings/mail', mail);
  }

  /** Back to MAILER_DSN of the .env file */
  resetMailServer(): Observable<AdminSettings> {
    return this.http.delete<AdminSettings>('api/admin/settings/mail/server');
  }

  /** Tests the values of the form, saved or not */
  testMail(mail: MailSettingsInput, recipient: string | null): Observable<CheckResult> {
    return this.http.post<CheckResult>('api/admin/mail/test', { ...mail, recipient });
  }

  signups(): Observable<AdminMember[]> {
    return this.http.get<AdminMember[]>('api/admin/signups');
  }

  approve(userId: number): Observable<AdminMember> {
    return this.http.post<AdminMember>(`api/admin/signups/${userId}/approve`, null);
  }

  reject(userId: number): Observable<void> {
    return this.http.post<void>(`api/admin/signups/${userId}/reject`, null);
  }

  members(): Observable<AdminMember[]> {
    return this.http.get<AdminMember[]>('api/admin/members');
  }

  addMember(member: {
    username: string;
    displayName: string;
    password: string;
    role: ClanRole;
    email: string | null;
  }): Observable<AdminMember> {
    return this.http.post<AdminMember>('api/admin/members', member);
  }

  changeRole(userId: number, role: ClanRole): Observable<AdminMember> {
    return this.http.put<AdminMember>(`api/admin/members/${userId}/role`, { role });
  }

  removeMember(userId: number): Observable<void> {
    return this.http.delete<void>(`api/admin/members/${userId}`);
  }
}
