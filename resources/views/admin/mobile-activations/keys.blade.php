@extends('layouts.app')

@section('title', 'License Keys — FastPos Admin')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-0 fw-bold">🔑 License Keys</h1>
      <p class="text-muted mb-0">Issue and manage FastPos Mobile license keys</p>
    </div>
    <a href="{{ route('admin.mobile-activations.index') }}" class="btn btn-outline-secondary">
      ← Back to Devices
    </a>
  </div>

  @if (session('success'))
  <div class="alert alert-success alert-dismissible fade show">
    ✅ {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  <div class="row g-4">
    {{-- Generate new key --}}
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-primary text-white fw-bold">Generate New License Key</div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.license-keys.store') }}">
            @csrf

            <div class="mb-3">
              <label class="form-label fw-semibold">Business (optional)</label>
              <select name="business_id" class="form-select">
                <option value="">— Any Business —</option>
                @foreach(\App\Business::orderBy('name')->get() as $biz)
                  <option value="{{ $biz->id }}">{{ $biz->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Plan</label>
              <select name="plan" class="form-select" required>
                <option value="trial">Trial (7 days)</option>
                <option value="basic" selected>Basic</option>
                <option value="professional">Professional</option>
                <option value="enterprise">Enterprise</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Max Devices</label>
              <input type="number" name="max_devices" class="form-control" value="1" min="1" max="999" required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Valid For (days)</label>
              <input type="number" name="valid_days" class="form-control" min="1"
                     placeholder="Leave blank for lifetime">
              <small class="text-muted">Leave blank for a lifetime licence</small>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Notes</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Customer name, order reference…"></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-bold">
              🎲 Generate Key
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- Keys table --}}
    <div class="col-md-8">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Key</th>
                  <th>Plan</th>
                  <th>Business</th>
                  <th>Devices</th>
                  <th>Expires</th>
                  <th>Status</th>
                  <th class="text-end pe-3">Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($keys as $key)
                <tr>
                  <td class="ps-3">
                    <code class="bg-light px-2 py-1 rounded" style="font-size:12px">{{ $key->key }}</code>
                  </td>
                  <td>
                    <span class="badge bg-secondary">{{ ucfirst($key->plan) }}</span>
                  </td>
                  <td>{{ $key->business?->name ?? '—' }}</td>
                  <td>
                    <span class="{{ $key->activations_count >= $key->max_devices ? 'text-danger fw-bold' : 'text-success' }}">
                      {{ $key->activations_count }} / {{ $key->max_devices }}
                    </span>
                  </td>
                  <td>
                    @if($key->valid_until)
                      <span class="{{ $key->valid_until->isPast() ? 'text-danger' : '' }}">
                        {{ $key->valid_until->format('d M Y') }}
                      </span>
                    @else
                      <span class="text-muted">Lifetime</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge {{ $key->status === 'active' ? 'bg-success' : 'bg-danger' }}">
                      {{ ucfirst($key->status) }}
                    </span>
                  </td>
                  <td class="text-end pe-3">
                    @if ($key->status === 'active')
                    <form method="POST"
                          action="{{ route('admin.license-keys.revoke', $key) }}"
                          class="d-inline">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger"
                              onclick="return confirm('Suspend this key and all its devices?')">
                        Revoke
                      </button>
                    </form>
                    @else
                      <span class="text-muted small">Revoked</span>
                    @endif
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">No license keys yet. Generate one →</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        @if($keys->hasPages())
        <div class="card-footer bg-transparent">{{ $keys->links() }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
