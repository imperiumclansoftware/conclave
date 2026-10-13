import { HttpErrorResponse } from '@angular/common/http';
import { apiErrorMessage } from './api-error';

describe('apiErrorMessage', () => {
  it('joins validation violations', () => {
    const error = new HttpErrorResponse({
      status: 422,
      error: {
        violations: [{ propertyPath: 'username', title: 'Cet identifiant est déjà utilisé.' }],
      },
    });
    expect(apiErrorMessage(error)).toBe('Cet identifiant est déjà utilisé.');
  });

  it('translates invalid credentials', () => {
    const error = new HttpErrorResponse({
      status: 401,
      error: { code: 401, message: 'Invalid credentials.' },
    });
    expect(apiErrorMessage(error)).toBe('Identifiant ou mot de passe incorrect.');
  });

  it('reports network errors', () => {
    expect(apiErrorMessage(new HttpErrorResponse({ status: 0 }))).toBe(
      'Impossible de joindre le serveur.',
    );
  });
});
