import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { ManageCookies } from '../../shared/manage-cookies';

/** Terms of use of the private clan app (public page, also readable before signing up). */
@Component({
  selector: 'app-terms-page',
  imports: [RouterLink, ManageCookies],
  templateUrl: './terms.page.html',
  styleUrl: './legal.scss',
})
export class TermsPage {
  private readonly auth = inject(AuthService);

  protected readonly updatedAt = '12 octobre 2026';
  protected readonly loggedIn = !!this.auth.token();
  protected readonly clanName = toSignal(
    this.auth.setup().pipe(
      map((setup) => setup.clanName ?? 'le clan'),
      catchError(() => of('le clan')),
    ),
    { initialValue: 'le clan' },
  );
}
