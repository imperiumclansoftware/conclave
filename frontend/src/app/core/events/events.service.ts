import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ClanEvent, EventInput, EventTypes } from '../api/models';

/** Events of the branches. */
@Injectable({ providedIn: 'root' })
export class EventsService {
  private readonly http = inject(HttpClient);

  types(): Promise<EventTypes> {
    return firstValueFrom(this.http.get<EventTypes>('api/events/types'));
  }

  /** Upcoming (and in progress) from the soonest, or past from the latest */
  list(period: 'upcoming' | 'past', branch: number | null = null): Promise<ClanEvent[]> {
    const params: Record<string, string> = { period };
    if (branch) {
      params['branch'] = String(branch);
    }
    return firstValueFrom(this.http.get<ClanEvent[]>('api/events', { params }));
  }

  /** Events taking place, even partly, between two instants (planning) */
  between(from: Date, to: Date, branch: number | null = null): Promise<ClanEvent[]> {
    const params: Record<string, string> = { from: from.toISOString(), to: to.toISOString() };
    if (branch) {
      params['branch'] = String(branch);
    }
    return firstValueFrom(this.http.get<ClanEvent[]>('api/events', { params }));
  }

  get(id: number): Promise<ClanEvent> {
    return firstValueFrom(this.http.get<ClanEvent>(`api/events/${id}`));
  }

  create(input: EventInput): Promise<ClanEvent> {
    return firstValueFrom(this.http.post<ClanEvent>('api/events', input));
  }

  update(id: number, input: EventInput): Promise<ClanEvent> {
    return firstValueFrom(this.http.put<ClanEvent>(`api/events/${id}`, input));
  }

  remove(id: number): Promise<void> {
    return firstValueFrom(this.http.delete<void>(`api/events/${id}`));
  }

  join(id: number): Promise<ClanEvent> {
    return firstValueFrom(this.http.put<ClanEvent>(`api/events/${id}/participation`, null));
  }

  leave(id: number): Promise<ClanEvent> {
    return firstValueFrom(this.http.delete<ClanEvent>(`api/events/${id}/participation`));
  }
}
