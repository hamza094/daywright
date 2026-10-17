# Email Change Security Decision

## Decision

**Email addresses are permanent account identifiers and cannot be changed after account creation.**

## Rationale

This security decision was made to eliminate a critical account takeover vulnerability identified in the production security audit (P1.2).

### Security Benefits

1. **Eliminates Account Takeover Risk**
   - No email change vulnerability to exploit
   - Prevents unauthorized email changes through compromised sessions
   - Removes the need for complex email verification flows

2. **Simpler Security Model**
   - Email serves as a permanent, immutable account identifier
   - Reduces attack surface significantly
   - No complex verification token management
   - No pending email states to handle

3. **Production Reliability**
   - Fewer edge cases to handle (expired tokens, stale links, etc.)
   - Simpler codebase with fewer failure modes
   - Easier to maintain and audit

### Implementation Details

#### Technical Changes

1. **Request Validation** (`app/Http/Requests/Api/V1/User/UserRequest.php`)
   - Email field is explicitly marked as `prohibited`
   - Removed email from profile update validation rules

2. **Data Transfer Object** (`app/DataTransferObjects/User/UpdateUserData.php`)
   - Email removed from user attributes allowlist
   - Email also excluded from info attributes (double protection)
   - Added security comment explaining the restriction

3. **Service Layer** (`app/Services/User/UserService.php`)
   - No changes needed - DTO filtering prevents email from reaching service
   - Service cannot bypass the restriction

4. **Frontend Component** (`resources/js/components/Profile/Edit.vue`)
   - Removed email input field from the edit profile form
   - Email now displayed as read-only static text
   - Added explanatory text about permanent email for security
   - Removed email from form data, validation, and reset methods

#### Security Validation

- Added test to verify email changes are rejected with 422 status
- Updated existing profile update tests to exclude email changes
- Verified email field is completely blocked at multiple layers

## Production Considerations

### User Impact

**Users who need to change their email:**

- Must create a new account with the desired email
- Can migrate data through appropriate export/import features if needed
- Contact support for special circumstances

**Account Recovery:**

- Email remains the primary account identifier
- Password recovery still functions normally
- 2FA provides additional security layer

### Industry Precedent

Many production applications use this approach:

- Email as permanent account identifier
- Account creation tied to email verification
- Email changes require new account creation

### Alternatives Considered

#### Email Verification Flow (Rejected)

The original audit plan proposed implementing a complex email change verification flow:

- New endpoint: `POST /api/v1/users/me/email-change-requests`
- Email verification tokens and pending states
- Notifications to old and new email addresses
- Recent password/2FA confirmation requirements

**Why this was rejected:**

- Complex implementation with many edge cases
- Additional maintenance burden
- Still introduces some security risk
- Longer development and testing timeline
- More failure modes to handle

#### Current Approach (Chosen)

**Advantages:**

- Maximum security - no email change vulnerability
- Simple implementation (~30 minutes vs hours/days)
- Easier to maintain and audit
- Fewer edge cases and failure modes
- Production-ready immediately

**Trade-offs:**

- Users cannot change email addresses
- Requires new account creation for email changes
- May require support process for edge cases

## Compliance and Legal

### GDPR Considerations

- Email as permanent identifier is compliant with GDPR
- Users maintain control over their account through other means
- Data export and deletion rights preserved
- No unauthorized data processing introduced

### Security Audit Compliance

This decision addresses the P1.2 finding from the production security audit:

- **Original Issue**: Email changes allowed through general profile updates without verification
- **Resolution**: Email changes completely prevented, eliminating the vulnerability
- **Audit Requirement**: "Separate account recovery changes from team/profile authority"
- **Implementation**: Email changes removed from profile authority entirely

## Conclusion

Preventing email changes entirely provides the strongest security posture for production deployment. This decision:

1. **Eliminates a critical security vulnerability** completely
2. **Provides simpler, more maintainable code**
3. **Reduces development and testing time** significantly
4. **Aligns with industry best practices** for account security
5. **Maintains compliance** with data protection regulations

The trade-off of requiring new account creation for email changes is acceptable given the significant security and operational benefits.

## References

- Production Security Audit: P1.2 - Separate account recovery changes from team/profile authority
- Implementation: Phase 1 Critical Security Fixes
- Date: 2026-09-10
