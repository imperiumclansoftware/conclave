import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';
import {
  AvatarSettings,
  AvatarSource,
  GamerNetwork,
  Me,
  MemberProfile,
  Profile,
} from '../api/models';
import { AuthService } from '../auth/auth.service';

export interface ProfileChanges {
  displayName: string;
  email: string | null;
  bio: string | null;
  gamerTags: Partial<Record<GamerNetwork, string>>;
}

/** Response of the avatar endpoints: the user, and their avatar settings. */
export type AvatarChange = Me & { avatar: AvatarSettings };

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);

  mine(): Observable<Profile> {
    return this.http.get<Profile>('api/me/profile');
  }

  update(changes: ProfileChanges): Observable<Profile> {
    return this.http
      .put<Profile>('api/me/profile', changes)
      .pipe(tap((profile) => this.auth.updateUser({ displayName: profile.displayName })));
  }

  uploadAvatar(file: File): Observable<AvatarChange> {
    const body = new FormData();
    body.append('avatar', file);
    return this.avatarChange(this.http.post<AvatarChange>('api/me/avatar', body));
  }

  removeAvatar(): Observable<AvatarChange> {
    return this.avatarChange(this.http.delete<AvatarChange>('api/me/avatar'));
  }

  /** Uses the Gravatar of this address (only its hash is kept by the server). */
  useGravatar(email: string): Observable<AvatarChange> {
    return this.avatarChange(this.http.put<AvatarChange>('api/me/avatar/gravatar', { email }));
  }

  removeGravatar(): Observable<AvatarChange> {
    return this.avatarChange(this.http.delete<AvatarChange>('api/me/avatar/gravatar'));
  }

  chooseAvatar(source: AvatarSource): Observable<AvatarChange> {
    return this.avatarChange(this.http.put<AvatarChange>('api/me/avatar/source', { source }));
  }

  private avatarChange(request: Observable<AvatarChange>): Observable<AvatarChange> {
    return request.pipe(tap((me) => this.auth.updateUser({ avatarUrl: me.avatarUrl })));
  }

  /** Theme of the account (null: follow the device), applied on every device. */
  setNotifications(notifications: Me['notifications']): Observable<Me> {
    return this.http
      .put<Me>('api/me/notifications', notifications)
      .pipe(tap((me) => this.auth.updateUser({ notifications: me.notifications })));
  }

  setFaction(faction: 'assassin' | 'templar' | null): Observable<Me> {
    return this.http
      .put<Me>('api/me/faction', { faction })
      .pipe(tap((me) => this.auth.updateUser({ faction: me.faction })));
  }

  changePassword(currentPassword: string, newPassword: string): Observable<void> {
    return this.http.put<void>('api/me/password', { currentPassword, newPassword });
  }

  member(id: number): Observable<MemberProfile> {
    return this.http.get<MemberProfile>(`api/members/${id}`);
  }
}
