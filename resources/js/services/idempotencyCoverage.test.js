import assert from 'node:assert/strict';
import test from 'node:test';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, resolve, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..');

const readSource = (relativePath) => {
  return readFileSync(resolve(repoRoot, relativePath), 'utf8');
};

const walkFiles = (directory) => {
  return readdirSync(directory).flatMap((entry) => {
    const fullPath = resolve(directory, entry);

    if (statSync(fullPath).isDirectory()) {
      return walkFiles(fullPath);
    }

    return [fullPath];
  });
};

const idempotentRouteCoverage = [
  {
    file: 'resources/js/components/Subscription.vue',
    patterns: [
      /createIdempotentRequest/,
      /this\.subscribeRequest = createIdempotentRequest\(\)/,
      /this\.swapSubscriptionRequest = createIdempotentRequest\(\)/,
      /this\.subscribeRequest\s*\.post\('\/users\/me\/subscription'/,
      /this\.swapSubscriptionRequest\s*\.patch\('\/users\/me\/subscription'/,
      /this\.subscribeRequest\?\.reset\(\)/,
      /this\.swapSubscriptionRequest\?\.reset\(\)/,
    ],
  },
  {
    file: 'resources/js/components/Profile/UserTokens.vue',
    patterns: [
      /this\.createTokenRequest = createIdempotentRequest\(\)/,
      /this\.createTokenRequest\s*\.post\('\/api-tokens'/,
      /this\.createTokenRequest\?\.reset\(\)/,
    ],
  },
  {
    file: 'resources/js/components/Profile/ProjectInvitation.vue',
    patterns: [
      /this\.acceptInvitationRequest = createIdempotentRequest\(\)/,
      /this\.rejectInvitationRequest = createIdempotentRequest\(\)/,
      /this\.acceptInvitationRequest\s*\.post\(`\/projects\/\$\{slug\}\/invitations\/accept`\)/,
      /this\.rejectInvitationRequest\s*\.post\(`\/projects\/\$\{slug\}\/invitations\/reject`\)/,
      /this\.acceptInvitationRequest\?\.reset\(\)/,
      /this\.rejectInvitationRequest\?\.reset\(\)/,
    ],
  },
  {
    file: 'resources/js/components/Project/Feature/Message.vue',
    patterns: [
      /this\.sendMessageRequest = createIdempotentRequest\(\)/,
      /this\.sendMessageRequest\s*\.post\('\/projects\/' \+ this\.slug \+ '\/messages'/,
      /this\.sendMessageRequest\?\.reset\(\)/,
    ],
  },
  {
    file: 'resources/js/components/Project/Panel/Features.vue',
    patterns: [
      /this\.inviteUserRequest = createIdempotentRequest\(\)/,
      /this\.inviteUserRequest\s*\.post\('\/projects\/' \+ this\.slug \+ '\/invitations'/,
      /this\.inviteUserRequest\?\.reset\(\)/,
    ],
  },
  {
    file: 'resources/js/components/Project/Panel/Modal/TaskMembers.vue',
    patterns: [
      /this\.assignMembersRequest = createIdempotentRequest\(\)/,
      /this\.assignMembersRequest\s*\.patch\(url\(this\.slug, taskId\) \+ '\/assign'/,
      /this\.assignMembersRequest\?\.reset\(\)/,
      /this\.assignMembersRequest\.reset\(\)/,
    ],
  },
  {
    file: 'resources/js/components/Project/Panel/TaskDetailModal.vue',
    patterns: [
      /this\.unassignMemberRequest = createIdempotentRequest\(\)/,
      /this\.unassignMemberRequest\s*\.patch\(url\(this\.slug, taskId\) \+ '\/unassign'/,
      /this\.unassignMemberRequest\?\.reset\(\)/,
    ],
  },
  {
    file: 'resources/js/components/Project/Meetings/MeetingModal.vue',
    patterns: [
      /this\.createMeetingRequest = createIdempotentRequest\(\)/,
      /this\.createMeetingRequest\s*\.post\(`\/projects\/\$\{this\.projectSlug\}\/meetings`/,
      /this\.createMeetingRequest\?\.reset\(\)/,
    ],
  },
  {
    file: 'resources/js/components/Project/Meetings/ViewModal.vue',
    patterns: [
      /this\.updateMeetingRequest = createIdempotentRequest\(\)/,
      /this\.updateMeetingRequest\s*\.patch\(`\/projects\/\$\{this\.projectSlug\}\/meetings\/\$\{id\}`/,
      /this\.updateMeetingRequest\?\.reset\(\)/,
      /this\.updateMeetingRequest\.reset\(\)/,
    ],
  },
];

test('current idempotent UI routes use dedicated helper instances and teardown resets', () => {
  for (const { file, patterns } of idempotentRouteCoverage) {
    const source = readSource(file);

    for (const pattern of patterns) {
      assert.match(source, pattern, `${file} is missing expected idempotency coverage pattern ${pattern}`);
    }
  }
});

test('Idempotency-Key is only set by the shared idempotent request helper', () => {
  const files = walkFiles(resolve(repoRoot, 'resources', 'js'));
  const headerWriters = files
    .filter((filePath) => !filePath.endsWith('.test.js'))
    .filter((filePath) => readFileSync(filePath, 'utf8').includes('Idempotency-Key'))
    .map((filePath) => relative(repoRoot, filePath).replaceAll('\\', '/'))
    .sort();

  assert.deepEqual(headerWriters, ['resources/js/services/IdempotencyRequestService.js']);
});
