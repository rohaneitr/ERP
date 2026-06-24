@extends('layouts.app')

@section('title', 'Mobile Activations — FastPos Admin')

@push('css')
<style>
.stat-card { border-radius: 12px; padding: 20px; }
.stat-card .stat-value { font-size: 2rem; font-weight: 900; margin: 4px 0; }
.stat-card .stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; opacity: .7; }
.device-card { border-radius: 8px; border: 1px solid #e5e7eb; padding: 16px; transition: box-shadow .2s; }
.device-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-active    { background: #d1fae5; color: #065f46; }
.badge-suspended { background: #fef3c7; color: #92400e; }
.badge-revoked   { background: #fee2e2; color: #991b1b; }
.badge-expired   { background: #f3f4f6; color: #6b7280; }
.online-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; background: #10b981; margin-right: 6px; }
.offline-dot { background: #9ca3af; }
.table th { font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-0 fw-bold">📱 Mobile Activations</h1>
      <p class="text-muted mb-0">Manage FastPos Mobile device licences</p>
    </div>
    <a href="{{ route('admin.license-keys.index') }}" class="btn btn-primary">
      🔑 Manage License Keys
    </a>
  </div>

  {{-- Flash messages --}}
  @if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    ✅ {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  {{-- Stats row --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
      <div class="stat-card bg-primary text-white">
        <div class="stat-label">Total Devices</div>
        <div class="stat-value">{{ $stats['total'] }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="stat-card bg-success text-white">
        <div class="stat-label">Active</div>
        <div class="stat-value">{{ $stats['active'] }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="stat-card bg-warning text-dark">
        <div class="stat-label">Suspended</div>
        <div class="stat-value">{{ $stats['suspended'] }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="stat-card bg-danger text-white">
        <div class="stat-label">Expired</div>
        <div class="stat-value">{{ $stats['expired'] }}</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="stat-card bg-info text-white">
        <div class="stat-label">Online (24h)</div>
        <div class="stat-value">{{ $stats['online_24h'] }}</div>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Search</label>
          <input type="text" name="search" class="form-control"
                 placeholder="License key, device name, fingerprint…"
                 value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="active"    {{ request('status') === 'active'    ? 'selected' : '' }}>Active</option>
            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
            <option value="revoked"   {{ request('status') === 'revoked'   ? 'selected' : '' }}>Revoked</option>
            <option value="expired"   {{ request('status') === 'expired'   ? 'selected' : '' }}>Expired</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
        </div>
        <div class="col-md-2">
          <a href="{{ route('admin.mobile-activations.index') }}" class="btn btn-outline-secondary w-100">Clear</a>
        </div>
      </form>
    </div>
  </div>

  {{-- Table --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Device</th>
              <th>License Key</th>
              <th>Business</th>
              <th>Status</th>
              <th>Last Seen</th>
              <th>Expires</th>
              <th>Syncs</th>
              <th class="text-end pe-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($activations as $activation)
            <tr>
              <td class="ps-3">
                <div class="d-flex align-items-center gap-2">
                  <span class="{{ $activation->last_seen_at && $activation->last_seen_at->diffInHours() < 24 ? 'online-dot' : 'online-dot offline-dot' }}"></span>
                  <div>
                    <div class="fw-semibold">{{ $activation->device_name ?? 'Unknown Device' }}</div>
                    <small class="text-muted">{{ $activation->device_brand }} {{ $activation->device_model }} · {{ strtoupper($activation->platform) }}</small>
                  </div>
                </div>
              </td>
              <td>
                <code class="bg-light px-2 py-1 rounded" style="font-size:12px">
                  {{ $activation->license_key }}
                </code>
              </td>
              <td>{{ $activation->business?->name ?? '—' }}</td>
              <td>
                <span class="status-badge badge-{{ $activation->status }}">
                  {{ $activation->status }}
                </span>
              </td>
              <td>
                @if($activation->last_seen_at)
                  <span title="{{ $activation->last_seen_at }}">
                    {{ $activation->last_seen_at->diffForHumans() }}
                  </span>
                @else
                  <span class="text-muted">Never</span>
                @endif
              </td>
              <td>
                @if($activation->expires_at)
                  <span class="{{ $activation->isExpired() ? 'text-danger' : 'text-success' }}">
                    {{ $activation->expires_at->format('d M Y') }}
                  </span>
                @else
                  <span class="text-muted">Lifetime</span>
                @endif
              </td>
              <td>{{ number_format($activation->sync_count) }}</td>
              <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                  <a href="{{ route('admin.mobile-activations.show', $activation) }}"
                     class="btn btn-outline-secondary" title="View">👁</a>

                  @if ($activation->status === 'active')
                    <form method="POST" action="{{ route('admin.mobile-activations.suspend', $activation) }}" class="d-inline">
                      @csrf
                      <button class="btn btn-outline-warning" title="Suspend"
                              onclick="return confirm('Suspend this device?')">⏸</button>
                    </form>
                    <form method="POST" action="{{ route('admin.mobile-activations.revoke', $activation) }}" class="d-inline">
                      @csrf
                      <button class="btn btn-outline-danger" title="Revoke"
                              onclick="return confirm('Permanently revoke this device? The user will be locked out.')">🚫</button>
                    </form>
                  @else
                    <form method="POST" action="{{ route('admin.mobile-activations.reactivate', $activation) }}" class="d-inline">
                      @csrf
                      <button class="btn btn-outline-success" title="Reactivate">▶</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                No mobile activations found.
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if ($activations->hasPages())
    <div class="card-footer bg-transparent">
      {{ $activations->links() }}
    </div>
    @endif
  </div>
</div>
@endsection
