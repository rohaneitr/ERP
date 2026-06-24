@extends('layouts.app')

@section('title', 'Mobile User & License Administration — FastPos Admin')

@push('css')
<style>
.stat-card { border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,.05); border: none; }
.stat-card .stat-value { font-size: 2rem; font-weight: 900; margin: 4px 0; }
.stat-card .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; opacity: .8; }
.status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-active    { background: #d1fae5; color: #065f46; }
.badge-inactive  { background: #fee2e2; color: #991b1b; }
.badge-pending   { background: #fef3c7; color: #92400e; }
.badge-approved  { background: #d1fae5; color: #065f46; }
.badge-rejected  { background: #f3f4f6; color: #6b7280; }
.table th { font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
.nav-tabs .nav-link { font-weight: 600; border: none; border-bottom: 3px solid transparent; color: #4b5563; }
.nav-tabs .nav-link.active { border: none; border-bottom: 3px solid #3b82f6; color: #2563eb; background: none; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-0 fw-bold">📱 Mobile User & License Administration</h1>
      <p class="text-muted mb-0">Secure administrative dashboard for accounts, activations, and audit trails</p>
    </div>
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createUserModal">
      ➕ Create Mobile User
    </button>
  </div>

  {{-- Notifications --}}
  @if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>Success!</strong> {{ session('success') }}
  </div>
  @endif

  @if (session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
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

  {{-- Navigation Tabs --}}
  <ul class="nav nav-tabs mb-4 border-bottom" id="adminTabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" id="users-tab" data-toggle="tab" href="#users" role="tab">👤 User Accounts</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="regs-tab" data-toggle="tab" href="#regs" role="tab">📩 Pending Registrations</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="audit-tab" data-toggle="tab" href="#audit" role="tab">📝 Security & Audit Logs</a>
    </li>
  </ul>

  <div class="tab-content" id="adminTabsContent">
    
    {{-- TAB 1: USER ACCOUNTS --}}
    <div class="tab-pane fade show active" id="users" role="tabpanel">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <form method="GET" action="{{ action('\App\Http\Controllers\MobileUserManagementController@index') }}" class="row g-2">
            <div class="col-md-6">
              <input type="text" name="search" class="form-control" placeholder="Search by name, email, username..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
              <a href="{{ action('\App\Http\Controllers\MobileUserManagementController@index') }}" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">User Details</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Force PW Change</th>
                  <th>Locked Out</th>
                  <th>Last Login</th>
                  <th class="text-end pe-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($users as $user)
                <tr>
                  <td class="ps-3">
                    <div class="fw-semibold">{{ $user->name }}</div>
                    <small class="text-muted">{{ $user->phone ?? 'No phone' }}</small>
                  </td>
                  <td><code>{{ $user->username }}</code></td>
                  <td>{{ $user->email }}</td>
                  <td>
                    <span class="status-badge badge-{{ $user->status === 'active' ? 'active' : 'inactive' }}">
                      {{ $user->status }}
                    </span>
                  </td>
                  <td>
                    @if ($user->must_change_password)
                      <span class="badge badge-warning">Required</span>
                    @else
                      <span class="badge badge-success">No</span>
                    @endif
                  </td>
                  <td>
                    @if ($user->locked_until && \Carbon\Carbon::parse($user->locked_until)->isFuture())
                      <span class="badge badge-danger" title="Until {{ $user->locked_until }}">Locked</span>
                    @else
                      <span class="badge badge-success">No</span>
                    @endif
                  </td>
                  <td>
                    @if ($user->last_login_at)
                      <span title="{{ $user->last_login_at }}">
                        {{ \Carbon\Carbon::parse($user->last_login_at)->diffForHumans() }}
                      </span>
                    @else
                      <span class="text-muted">Never</span>
                    @endif
                  </td>
                  <td class="text-end pe-3">
                    <a href="{{ action('\App\Http\Controllers\MobileUserManagementController@show', $user->id) }}" class="btn btn-xs btn-outline-info" title="Manage Details">
                      ⚙️ Manage
                    </a>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="8" class="text-center py-4 text-muted">No mobile users found.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        @if ($users->hasPages())
        <div class="card-footer bg-transparent">
          {!! $users->appends(request()->except('users_page'))->links() !!}
        </div>
        @endif
      </div>
    </div>

    {{-- TAB 2: PENDING REGISTRATIONS QUEUE --}}
    <div class="tab-pane fade" id="regs" role="tabpanel">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Full Name</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Requested On</th>
                  <th>Verification</th>
                  <th>Status</th>
                  <th class="text-end pe-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($registrations as $reg)
                <tr>
                  <td class="ps-3 fw-semibold">{{ $reg->first_name }} {{ $reg->last_name }}</td>
                  <td><code>{{ $reg->username }}</code></td>
                  <td>{{ $reg->email }}</td>
                  <td>{{ $reg->phone ?? '—' }}</td>
                  <td>{{ \Carbon\Carbon::parse($reg->created_at)->format('Y-m-d H:i') }}</td>
                  <td>
                    @if ($reg->verified_at)
                      <span class="badge badge-success" title="Verified at {{ $reg->verified_at }}">Email Verified</span>
                    @else
                      <span class="badge badge-warning">Unverified</span>
                    @endif
                  </td>
                  <td>
                    <span class="status-badge badge-{{ $reg->status }}">
                      {{ $reg->status }}
                    </span>
                  </td>
                  <td class="text-end pe-3">
                    @if ($reg->status === 'pending')
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-success" data-toggle="modal" data-target="#approveRegModal{{ $reg->id }}" title="Approve Request">
                        ✓ Approve
                      </button>
                      <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectRegModal{{ $reg->id }}" title="Reject Request">
                        ✗ Reject
                      </button>
                    </div>

                    {{-- Approve Modal --}}
                    <div class="modal fade" id="approveRegModal{{ $reg->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@approveRegistration', $reg->id) }}">
                          @csrf
                          <div class="modal-content text-left">
                            <div class="modal-header">
                              <h5 class="modal-title">Approve Registration request — {{ $reg->username }}</h5>
                              <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                              <p>Select subscription plan to allocate to this new mobile user:</p>
                              <div class="form-group">
                                <label class="fw-bold">Subscription Plan *</label>
                                <select name="plan_id" class="form-control" required>
                                  @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->duration_days }} days, {{ $plan->max_devices }} devices, ${{ $plan->price }})</option>
                                  @endforeach
                                </select>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-success">Confirm & Activate User</button>
                            </div>
                          </div>
                        </form>
                      </div>
                    </div>

                    {{-- Reject Modal --}}
                    <div class="modal fade" id="rejectRegModal{{ $reg->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                      <div class="modal-dialog" role="document">
                        <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@rejectRegistration', $reg->id) }}">
                          @csrf
                          <div class="modal-content text-left">
                            <div class="modal-header">
                              <h5 class="modal-title">Reject Registration Request</h5>
                              <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                              <div class="form-group">
                                <label class="fw-bold">Rejection Reason *</label>
                                <textarea name="reason" class="form-control" rows="3" required placeholder="Specify why the request is rejected..."></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-danger">Confirm Reject</button>
                            </div>
                          </div>
                        </form>
                      </div>
                    </div>
                    @else
                      <span class="text-muted">{{ $reg->rejection_reason ?: 'Processed' }}</span>
                    @endif
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="8" class="text-center py-4 text-muted">No pending registrations.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        @if ($registrations->hasPages())
        <div class="card-footer bg-transparent">
          {!! $registrations->appends(request()->except('regs_page'))->links() !!}
        </div>
        @endif
      </div>
    </div>

    {{-- TAB 3: IMMUTABLE AUDIT LOGS --}}
    <div class="tab-pane fade" id="audit" role="tabpanel">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Timestamp</th>
                  <th>Event Type</th>
                  <th>Target Resource</th>
                  <th>Done By</th>
                  <th>IP Address</th>
                  <th>Changes (Old ➔ New)</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($auditLogs as $log)
                <tr>
                  <td class="ps-3">
                    <code>{{ $log->created_at }}</code>
                  </td>
                  <td>
                    <span class="badge badge-secondary">{{ $log->event_type }}</span>
                  </td>
                  <td>
                    @if($log->subject_type)
                      <small class="text-muted">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</small>
                    @else
                      —
                    @endif
                  </td>
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
                      <button type="button" class="btn btn-xs btn-outline-secondary" data-toggle="collapse" data-target="#diffCollapse{{ $log->id }}">
                        View Diff
                      </button>
                      <div class="collapse mt-2" id="diffCollapse{{ $log->id }}">
                        <div class="p-2 bg-light border rounded" style="font-size: 11px;">
                          <strong>Old Values:</strong>
                          <pre class="mb-1">{{ json_encode(json_decode($log->old_values), JSON_PRETTY_PRINT) }}</pre>
                          <strong>New Values:</strong>
                          <pre class="mb-0">{{ json_encode(json_decode($log->new_values), JSON_PRETTY_PRINT) }}</pre>
                        </div>
                      </div>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">No audit logs found.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        @if ($auditLogs->hasPages())
        <div class="card-footer bg-transparent">
          {!! $auditLogs->appends(request()->except('audit_page'))->links() !!}
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Create User Modal --}}
<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form method="POST" action="{{ action('\App\Http\Controllers\MobileUserManagementController@store') }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="createUserModalLabel">Create Admin Mobile User Account</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="fw-bold">First Name *</label>
              <input type="text" name="first_name" class="form-control" required placeholder="Enter first name">
            </div>
            <div class="col-md-6 form-group">
              <label class="fw-bold">Last Name *</label>
              <input type="text" name="last_name" class="form-control" required placeholder="Enter last name">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="fw-bold">Username *</label>
              <input type="text" name="username" class="form-control" required placeholder="For mobile login (alphanumeric)">
            </div>
            <div class="col-md-6 form-group">
              <label class="fw-bold">Email Address *</label>
              <input type="email" name="email" class="form-control" required placeholder="user@domain.com">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="fw-bold">Phone Number</label>
              <input type="text" name="phone" class="form-control" placeholder="+123456789">
            </div>
            <div class="col-md-6 form-group">
              <label class="fw-bold">Initial Password *</label>
              <input type="password" name="password" class="form-control" required placeholder="Minimum 8 characters">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="fw-bold">Subscription License Plan *</label>
              <select name="plan_id" class="form-control" required>
                @foreach($plans as $plan)
                  <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->duration_days }} days, {{ $plan->max_devices }} devices, ${{ $plan->price }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label class="fw-bold">Account Status *</label>
              <select name="status" class="form-control" required>
                <option value="active">Active & Immediately Ready</option>
                <option value="inactive">Suspended / Deactivated</option>
              </select>
            </div>
          </div>
          <div class="alert alert-info py-2 mt-3" style="font-size:12px">
            ℹ️ Admin-created accounts are instantly available for mobile activation. Users will be forced to change their password on first login for security.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
      </div>
    </form>
  </div>
</div>

@endsection
