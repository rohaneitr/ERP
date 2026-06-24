@extends('layouts.app')

@section('title', 'Manage Mobile User Detail — FastPos Admin')

@push('css')
<style>
.status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-active    { background: #d1fae5; color: #065f46; }
.badge-inactive  { background: #fee2e2; color: #991b1b; }
.badge-blocked   { background: #fef3c7; color: #92400e; }
.badge-revoked   { background: #f3f4f6; color: #6b7280; }
.badge-trial     { background: #dbeafe; color: #1e40af; }
.nav-pills .nav-link.active { background-color: #3b82f6; color: #fff; }
.nav-pills .nav-link { font-weight: 600; color: #4b5563; border-radius: 8px; }
.card-header-gray { background-color: #f9fafb; border-bottom: 1px solid #f3f4f6; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">

  {{-- Back Navigation --}}
  <div class="mb-4">
    <a href="{{ action('\App\Http\Controllers\MobileUserManagementController@index') }}" class="text-decoration-none fw-semibold">
      ⬅ Back to User List
    </a>
  </div>

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h1 class="h3 mb-0 fw-bold">👤 Manage Mobile User: {{ $user->name }}</h1>
      <p class="text-muted mb-0">Manage security settings, device bindings, subscription plans, and review audit trail</p>
    </div>
    <div class="d-flex gap-2">
      @if ($user->locked_until && \Carbon\Carbon::parse($user->locked_until)->isFuture())
        <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@unlock', $user->id) }}">
          @csrf
          <button type="submit" class="btn btn-warning">🔓 Unlock Account</button>
        </form>
      @endif
      <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@forceLogout', $user->id) }}">
        @csrf
        <button type="submit" class="btn btn-danger" onclick="return confirm('Revoke all current mobile login sessions? The user will be instantly logged out.')">
          🚪 Force Logout All Sessions
        </button>
      </form>
    </div>
  </div>

  {{-- Flash Messages --}}
  @if (session('success'))
  <div class="alert alert-success">
    <strong>Success!</strong> {{ session('success') }}
  </div>
  @endif

  @if (session('error'))
  <div class="alert alert-danger">
    <strong>Error!</strong> {{ session('error') }}
  </div>
  @endif

  @if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="row g-4">
    
    {{-- Side Tabs Menu --}}
    <div class="col-md-3">
      <div class="card border-0 shadow-sm p-3">
        <div class="nav flex-column nav-pills" id="detailTabs" role="tablist">
          <a class="nav-link active mb-2" id="profile-tab" data-toggle="pill" href="#profile" role="tab">👤 Profile Details</a>
          <a class="nav-link mb-2" id="license-tab" data-toggle="pill" href="#license" role="tab">💳 License & Plan</a>
          <a class="nav-link mb-2" id="devices-tab" data-toggle="pill" href="#devices" role="tab">📱 Devices ({{ $devices->count() }})</a>
          <a class="nav-link mb-2" id="security-tab" data-toggle="pill" href="#security" role="tab">🔒 Security Actions</a>
          <a class="nav-link" id="user-audit-tab" data-toggle="pill" href="#user-audit" role="tab">📝 Audit Timeline</a>
        </div>
      </div>
    </div>

    {{-- Tabs Content --}}
    <div class="col-md-9">
      <div class="tab-content" id="detailTabsContent">
        
        {{-- Profile Details Tab --}}
        <div class="tab-pane fade show active" id="profile" role="tabpanel">
          <div class="card border-0 shadow-sm">
            <div class="card-header card-header-gray py-3">
              <h5 class="mb-0 fw-bold">Update Profile Details</h5>
            </div>
            <div class="card-body">
              <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@update', $user->id) }}">
                @csrf
                @method('PUT')
                <div class="row">
                  <div class="col-md-6 form-group">
                    <label class="fw-bold">Full Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                  </div>
                  <div class="col-md-6 form-group">
                    <label class="fw-bold">Email Address *</label>
                    <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                  </div>
                </div>
                <div class="row mt-3">
                  <div class="col-md-6 form-group">
                    <label class="fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="{{ $user->phone }}">
                  </div>
                  <div class="col-md-6 form-group">
                    <label class="fw-bold">Account Status *</label>
                    <select name="status" class="form-control" required>
                      <option value="active" {{ $user->status === 'active' ? 'selected' : '' }}>Active</option>
                      <option value="inactive" {{ $user->status === 'inactive' ? 'selected' : '' }}>Inactive / Deactivated</option>
                    </select>
                  </div>
                </div>
                <button type="submit" class="btn btn-primary mt-4">Save Profile Changes</button>
              </form>
            </div>
          </div>
        </div>

        {{-- License & Plan Tab --}}
        <div class="tab-pane fade" id="license" role="tabpanel">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header card-header-gray py-3">
              <h5 class="mb-0 fw-bold">Current Subscription details</h5>
            </div>
            <div class="card-body">
              @if ($subscription)
                <div class="row">
                  <div class="col-md-6">
                    <p class="mb-2"><strong>Plan Name:</strong> {{ $subscription->plan_name }}</p>
                    <p class="mb-2"><strong>Status:</strong> 
                      <span class="status-badge badge-{{ $subscription->status }}">
                        {{ $subscription->status }}
                      </span>
                    </p>
                    <p class="mb-2"><strong>Max Devices Limit:</strong> {{ $subscription->max_devices_override ?: 'Standard Plan Limit' }}</p>
                  </div>
                  <div class="col-md-6">
                    <p class="mb-2"><strong>Activated on:</strong> {{ $subscription->starts_at }}</p>
                    <p class="mb-2"><strong>Expires on:</strong> 
                      @if($subscription->expires_at)
                        <span class="{{ \Carbon\Carbon::parse($subscription->expires_at)->isPast() ? 'text-danger fw-bold' : 'text-success fw-bold' }}">
                          {{ $subscription->expires_at }} ({{ \Carbon\Carbon::parse($subscription->expires_at)->diffForHumans() }})
                        </span>
                      @else
                        <span class="text-muted">Lifetime Subscription</span>
                      @endif
                    </p>
                  </div>
                </div>
              @else
                <div class="alert alert-warning">No subscription records found. Create or assign a subscription.</div>
              @endif
            </div>
          </div>

          <div class="card border-0 shadow-sm">
            <div class="card-header card-header-gray py-3">
              <h5 class="mb-0 fw-bold">Extend Subscription validity</h5>
            </div>
            <div class="card-body">
              <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@extendSubscription', $user->id) }}">
                @csrf
                <div class="row align-items-end">
                  <div class="col-md-6 form-group mb-0">
                    <label class="fw-bold">Validity Extension Period *</label>
                    <select name="days" class="form-control" required>
                      <option value="30">Extend by 30 days (1 Month)</option>
                      <option value="90">Extend by 90 days (3 Months)</option>
                      <option value="180">Extend by 180 days (6 Months)</option>
                      <option value="365">Extend by 365 days (1 Year)</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <button type="submit" class="btn btn-success">✓ Process Extension</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        {{-- Devices Tab --}}
        <div class="tab-pane fade" id="devices" role="tabpanel">
          <div class="card border-0 shadow-sm">
            <div class="card-header card-header-gray py-3">
              <h5 class="mb-0 fw-bold">Registered Mobile Devices</h5>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Device Specs</th>
                      <th>Fingerprint (SHA256)</th>
                      <th>OS & App</th>
                      <th>Last Heartbeat</th>
                      <th>Status</th>
                      <th class="text-end pe-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse ($devices as $device)
                    <tr>
                      <td class="ps-3">
                        <div class="fw-bold">{{ $device->device_name ?: 'Unknown' }}</div>
                        <small class="text-muted">{{ $device->device_brand }} {{ $device->device_model }} · {{ strtoupper($device->platform) }}</small>
                      </td>
                      <td><code>{{ substr($device->device_fingerprint, 0, 16) }}...</code></td>
                      <td>
                        <small class="d-block">OS: {{ $device->os_version ?: '—' }}</small>
                        <small class="d-block">App: v{{ $device->app_version ?: '—' }}</small>
                      </td>
                      <td>
                        @if ($device->last_seen_at)
                          <small class="d-block" title="{{ $device->last_seen_at }}">{{ $device->last_seen_at->diffForHumans() }}</small>
                          <small class="text-muted d-block">{{ $device->last_seen_ip }}</small>
                        @else
                          <span class="text-muted">Never</span>
                        @endif
                      </td>
                      <td>
                        <span class="status-badge badge-{{ $device->status }}">
                          {{ $device->status }}
                        </span>
                      </td>
                      <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                          @if ($device->status === 'active')
                            <button type="button" class="btn btn-outline-warning" data-toggle="modal" data-target="#blockDeviceModal{{ $device->id }}" title="Block Device">
                              ⏸ Block
                            </button>
                            <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@revokeDevice', [$user->id, $device->id]) }}" class="d-inline">
                              @csrf
                              <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Revoke this device registration? The user will have to register their device again.')">
                                🚫 Revoke
                              </button>
                            </form>
                          @elseif ($device->status === 'blocked')
                            <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@unblockDevice', [$user->id, $device->id]) }}" class="d-inline">
                              @csrf
                              <button type="submit" class="btn btn-outline-success">
                                ▶ Unblock
                              </button>
                            </form>
                          @endif
                        </div>

                        {{-- Block Reason Modal --}}
                        <div class="modal fade" id="blockDeviceModal{{ $device->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                          <div class="modal-dialog" role="document">
                            <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@blockDevice', [$user->id, $device->id]) }}">
                              @csrf
                              <div class="modal-content text-left">
                                <div class="modal-header">
                                  <h5 class="modal-title">Block Device Binding</h5>
                                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                  <div class="form-group">
                                    <label class="fw-bold">Specify Reason for Blocking *</label>
                                    <input type="text" name="reason" class="form-control" required placeholder="e.g. Device reported lost, suspicious logs">
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                  <button type="submit" class="btn btn-danger">Confirm Block</button>
                                </div>
                              </div>
                            </form>
                          </div>
                        </div>
                      </td>
                    </tr>
                    @empty
                    <tr>
                      <td colspan="6" class="text-center py-4 text-muted">No registered devices for this user account.</td>
                    </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        {{-- Security Actions Tab --}}
        <div class="tab-pane fade" id="security" role="tabpanel">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header card-header-gray py-3">
              <h5 class="mb-0 fw-bold">Manual Security Actions</h5>
            </div>
            <div class="card-body">
              <div class="d-flex gap-3 flex-wrap">
                <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@forcePasswordChange', $user->id) }}">
                  @csrf
                  <button type="submit" class="btn btn-outline-warning">
                    ⚠️ Force Password Change on Next Login
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm">
            <div class="card-header card-header-gray py-3">
              <h5 class="mb-0 fw-bold">Admin Password override</h5>
            </div>
            <div class="card-body">
              <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@resetPassword', $user->id) }}">
                @csrf
                <div class="row align-items-end">
                  <div class="col-md-6 form-group mb-0">
                    <label class="fw-bold">New Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters">
                  </div>
                  <div class="col-md-6">
                    <button type="submit" class="btn btn-danger">✓ Force Override Password</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        {{-- Audit Timeline Tab --}}
        <div class="tab-pane fade" id="user-audit" role="tabpanel">
          <div class="card border-0 shadow-sm">
            <div class="card-header card-header-gray py-3">
              <h5 class="mb-0 fw-bold">Security & Operations Audit Trail</h5>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Timestamp</th>
                      <th>Event</th>
                      <th>Performed By</th>
                      <th>IP Address</th>
                      <th>Diff (Click to View)</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse ($auditLogs as $log)
                    <tr>
                      <td class="ps-3"><code>{{ $log->created_at }}</code></td>
                      <td><span class="badge badge-secondary">{{ $log->event_type }}</span></td>
                      <td>
                        @if ($log->causer_type === 'guest')
                          <span class="text-muted">guest</span>
                        @else
                          <small class="text-primary">{{ class_basename($log->causer_type) }} #{{ $log->causer_id }}</small>
                        @endif
                      </td>
                      <td><code>{{ $log->causer_ip }}</code></td>
                      <td>
                        @if ($log->old_values || $log->new_values)
                          <button type="button" class="btn btn-xs btn-outline-secondary" data-toggle="collapse" data-target="#userDiffCollapse{{ $log->id }}">
                            View
                          </button>
                          <div class="collapse mt-2" id="userDiffCollapse{{ $log->id }}">
                            <div class="p-2 bg-light border rounded" style="font-size:10px">
                              <strong>Old:</strong>
                              <pre class="mb-1">{{ json_encode(json_decode($log->old_values), JSON_PRETTY_PRINT) }}</pre>
                              <strong>New:</strong>
                              <pre class="mb-0">{{ json_encode(json_decode($log->new_values), JSON_PRETTY_PRINT) }}</pre>
                            </div>
                          </div>
                        @else
                          —
                        @endif
                      </td>
                    </tr>
                    @empty
                    <tr>
                      <td colspan="5" class="text-center py-4 text-muted">No audit trail records for this user account.</td>
                    </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>

</div>
@endsection
