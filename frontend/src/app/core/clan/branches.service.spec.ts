import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Branch } from '../api/models';
import { BranchesService } from './branches.service';

const branch = (id: number, name: string, memberIds: number[]): Branch => ({
  id,
  name,
  description: null,
  crest: null,
  publishesNews: false,
  approvalRequired: false,
  pendingIds: [],
  active: true,
  memberIds,
});

describe('BranchesService', () => {
  let service: BranchesService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(BranchesService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  async function load(...branches: Branch[]): Promise<void> {
    const loading = service.load();
    http.expectOne('api/clan/branches').flush(branches);
    await loading;
  }

  it('lists the branches of each member', async () => {
    await load(branch(1, 'Console', [7]), branch(2, 'PC', [7, 8]));

    expect(
      service
        .byMember()
        .get(7)
        ?.map((b) => b.name),
    ).toEqual(['Console', 'PC']);
    expect(
      service
        .byMember()
        .get(8)
        ?.map((b) => b.name),
    ).toEqual(['PC']);
    expect(service.byMember().has(9)).toBe(false);
  });

  it('joins and leaves a branch', async () => {
    await load(branch(2, 'PC', [8]));

    const joining = service.join(2);
    const req = http.expectOne('api/clan/branches/2/membership');
    expect(req.request.method).toBe('PUT');
    req.flush(branch(2, 'PC', [7, 8]));
    await joining;
    expect(
      service
        .byMember()
        .get(7)
        ?.map((b) => b.id),
    ).toEqual([2]);

    const leaving = service.leave(2);
    const leave = http.expectOne('api/clan/branches/2/membership');
    expect(leave.request.method).toBe('DELETE');
    leave.flush(branch(2, 'PC', [8]));
    await leaving;
    expect(service.byMember().has(7)).toBe(false);
  });

  it('shows a branch once activated, hides it once deactivated, and keeps them sorted', async () => {
    await load(branch(1, 'Console', []), branch(2, 'PC', []));

    const activating = service.setActive(3, true);
    const activate = http.expectOne('api/admin/branches/3/active');
    expect(activate.request.body).toEqual({ active: true });
    activate.flush(branch(3, 'Équipe', []));
    await activating;
    expect(service.branches().map((b) => b.name)).toEqual(['Console', 'Équipe', 'PC']);

    const renaming = service.update(1, {
      name: 'Rétro',
      description: null,
      publishesNews: false,
      approvalRequired: false,
    });
    http.expectOne('api/admin/branches/1').flush(branch(1, 'Rétro', []));
    await renaming;
    expect(service.branches().map((b) => b.name)).toEqual(['Équipe', 'PC', 'Rétro']);

    const deactivating = service.setActive(2, false);
    http.expectOne('api/admin/branches/2/active').flush({ ...branch(2, 'PC', []), active: false });
    await deactivating;
    expect(service.branches().map((b) => b.name)).toEqual(['Équipe', 'Rétro']);
  });
});
