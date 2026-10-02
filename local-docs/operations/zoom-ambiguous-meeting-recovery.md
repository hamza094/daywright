# Zoom Ambiguous Meeting Recovery Runbook

## Overview

This runbook documents the manual resolution process for Zoom meetings in `create_unknown`. This state means Daywright cannot determine whether Zoom created the meeting.

## When to Use This Runbook

Use this process when:

- Automatic recovery has stopped after its bounded attempts
- A `create_unknown` meeting has `sync_available_at = null`
- The user reports that the meeting exists in Zoom but Daywright shows it as failed/unknown
- Incident response requires manual intervention

## Prerequisites

- Access to the Daywright production environment
- Ability to run Artisan commands on the server
- Access to Zoom account to verify meeting existence
- Active incident/ticket reference (required for audit trail)

## Verification Steps

### 1. Verify Local Meeting State

```bash
php artisan tinker
>>> $meeting = App\Models\Meeting::find($meeting_id);
>>> $meeting->sync_status;
>>> $meeting->sync_operation_id;
>>> $meeting->sync_error;
>>> $meeting->sync_available_at;
>>> $meeting->sync_claim_token;
>>> $meeting->sync_lease_expires_at;
```

Expected state for manual resolution:

- `sync_status = create_unknown`
- `sync_available_at = null`
- `sync_claim_token = null`
- `sync_lease_expires_at = null`

### 2. Verify Zoom Meeting Existence

Check the Zoom account for the meeting:

- Log into Zoom web interface
- Use topic, time, or host only to locate a possible candidate
- Never treat those fields as proof that it is the same operation
- If a candidate is found, note the Zoom meeting ID
- Verify the meeting was created via API (not manual creation)

### 3. Verify Operation ID

If available, check if the Zoom meeting has the tracking field:

- In Zoom meeting settings, look for custom tracking fields
- Verify "Daywright Operation ID" matches the local `sync_operation_id`
- This provides strong evidence of correlation

## Resolution Command

### Activate Meeting (Meeting Exists in Zoom)

```bash
php artisan meetings:resolve-ambiguous \
  {meeting_id} \
   --zoom-meeting-id={zoom_meeting_id} \
   --reference={incident_reference}
```

**Parameters:**

- `meeting_id`: Local Daywright meeting ID
- `--zoom-meeting-id`: The Zoom meeting ID (required for activate)
- `--reference`: Incident/ticket reference (always required)
- `--force`: Skip confirmation only; it never bypasses the reference requirement

**What it does:**

- Fetches the meeting details from Zoom API
- Saves Zoom meeting ID, join URL, start URL, and status
- Marks meeting as `active`
- Clears all recovery fields (claim token, lease, available_at)
- Logs the action with ticket reference

### Fail Meeting (Meeting Does Not Exist in Zoom)

```bash
php artisan meetings:resolve-ambiguous \
  {meeting_id} \
   --mark-failed \
   --reference={incident_reference}
```

**Parameters:**

- `meeting_id`: Local Daywright meeting ID
- `--mark-failed`: Confirm that Zoom did not create the meeting
- `--reference`: Incident/ticket reference (always required)
- `--force`: Skip confirmation only; it never bypasses the reference requirement

**What it does:**

- Marks meeting as `failed`
- Sets `sync_error = manually_confirmed_not_created`
- Clears all recovery fields
- Logs the action with ticket reference

## Safety Checks

The command enforces these safety checks:

1. **State Validation**: Only resolves `create_unknown` meetings after automatic recovery has stopped and no worker owns a claim
2. **Zoom Verification**: For activation, verifies the Zoom meeting and exact operation ID via API
3. **Ticket Requirement**: Always requires an incident/ticket reference
4. **Confirmation Prompt**: Requires interactive confirmation unless `--force` is supplied
5. **Audit Logging**: All actions are logged with meeting ID, action, and ticket reference

## Post-Resolution Verification

### After Activation

1. Verify the meeting now shows as `active`:

   ```bash
   php artisan tinker
   >>> $meeting = App\Models\Meeting::find($meeting_id);
   >>> $meeting->sync_status; // Should be "active"
   >>> $meeting->meeting_id; // Should be set
   ```

2. Verify the user can start the meeting:
   - User should be able to obtain a start token
   - Join URL should work correctly

### After Failure

1. Verify the meeting is marked as `failed`:

   ```bash
   php artisan tinker
   >>> $meeting = App\Models\Meeting::find($meeting_id);
   >>> $meeting->sync_status; // Should be "failed"
   ```

2. Inform the user that the meeting was not created in Zoom
3. Suggest the user create a new meeting

## Important Notes

### What NOT to Do

- **Never** use direct SQL updates to resolve ambiguous meetings
- **Never** mark a meeting as `active` without verifying it exists in Zoom
- **Never** call the Zoom create endpoint during manual resolution
- **Never** bypass the ticket/reference requirement
- **Never** resolve a meeting that is already in `active` or `failed` state

### When to Escalate

Escalate to engineering if:

- Multiple meetings are stuck in ambiguous states
- The automatic recovery commands are failing consistently
- You cannot verify the Zoom meeting existence
- The operation ID tracking field is not working as expected

## Audit Trail

All manual resolutions are logged with:

- Meeting ID
- Action performed (activate/fail)
- Zoom meeting ID (if activating)
- Ticket reference
- Timestamp

The command does not authenticate an individual operator. The deployment platform or server access logs must provide operator identity when that audit requirement applies.

Review logs after resolution:

```bash
tail -f storage/logs/laravel.log | grep "Manual Zoom meeting resolution completed"
```

## Related Commands

### Check for Ambiguous Meetings

```bash
php artisan tinker
>>> App\Models\Meeting::where('sync_status', 'create_unknown')->whereNull('sync_available_at')->get(['id', 'sync_status', 'sync_operation_id', 'sync_error']);
```

### Run Automatic Recovery

```bash
php artisan meetings:recover-ambiguous --limit=50
```

This runs the scheduled recovery process manually. Try this before manual resolution.

## Troubleshooting

### Command Fails "Meeting not found"

- Verify the meeting ID is correct
- Check if the meeting was already resolved by another process

### Command Fails "Zoom meeting could not be verified for this operation"

- Verify the Zoom meeting ID is correct
- Check if the meeting was deleted in Zoom
- Verify the Zoom account connection is active

### Command Fails "Meeting is not awaiting manual recovery"

- The meeting may have already been resolved
- Check the current sync status using tinker

## Support

For issues with this runbook:

- Contact the engineering team
- Reference the incident/ticket number
- Include the command output and logs
