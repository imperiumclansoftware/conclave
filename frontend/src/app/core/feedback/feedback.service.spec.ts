import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { AuthService } from '../auth/auth.service';
import { FeedbackService, ticketPage } from './feedback.service';

describe('FeedbackService', () => {
  let service: FeedbackService;
  let backend: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: AuthService,
          useValue: { user: signal({ displayName: 'Papa', email: 'papa@example.com' }) },
        },
      ],
    });
    service = TestBed.inject(FeedbackService);
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    delete window.AvengerTickets;
    document.head.querySelectorAll('script[data-product]').forEach((script) => script.remove());
  });

  it('is not available while the server has no Avenger', async () => {
    const loading = service.load();
    backend.expectOne('api/feedback').flush({ widgetUrl: null, product: null });
    await loading;

    expect(service.available()).toBe(false);
  });

  it('loads the widget on the first click and prefills the member', async () => {
    const loading = service.load();
    backend
      .expectOne('api/feedback')
      .flush({ widgetUrl: 'https://avenger.example.org/widget.js', product: 'conclave' });
    await loading;
    expect(service.available()).toBe(true);
    expect(document.head.querySelector('script[data-product]')).toBeNull();

    const opening = service.open();
    await Promise.resolve();
    await Promise.resolve();
    const script = document.head.querySelector<HTMLScriptElement>('script[data-product]')!;
    expect([script.src, script.dataset['product'], script.dataset['button']]).toEqual([
      'https://avenger.example.org/widget.js',
      'conclave',
      'false',
    ]);

    // What widget.js defines once loaded
    const open = vi.fn();
    window.AvengerTickets = { open };
    script.dispatchEvent(new Event('load'));
    await opening;

    expect(open).toHaveBeenCalledWith({ type: 'bug', name: 'Papa', email: 'papa@example.com' });
    backend.verify();
  });

  it('opens the form of Avenger in a new tab when the widget does not load', async () => {
    const opened = vi.spyOn(window, 'open').mockReturnValue(null);
    const loading = service.load();
    backend.expectOne('api/feedback').flush({
      widgetUrl: 'https://avenger.example.org/absproxy/4300/widget.js',
      product: 'conclave',
    });
    await loading;

    const opening = service.open();
    await Promise.resolve();
    await Promise.resolve();
    document.head.querySelector('script[data-product]')!.dispatchEvent(new Event('error'));

    await expect(opening).rejects.toThrow('Avenger unreachable');
    expect(opened).toHaveBeenCalledWith(
      'https://avenger.example.org/absproxy/4300/produits/conclave/tickets/nouveau',
      '_blank',
      'noopener',
    );
    opened.mockRestore();
  });

  it('builds the address of the ticket form', () => {
    expect(ticketPage('https://avenger.example.org/widget.js', 'mon produit')).toBe(
      'https://avenger.example.org/produits/mon%20produit/tickets/nouveau',
    );
  });
});
