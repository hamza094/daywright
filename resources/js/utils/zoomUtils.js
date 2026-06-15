import axios from 'axios';
import ZoomMtgEmbedded from '@zoom/meetingsdk/embedded';
import { getObjectData } from './apiResponse.js';

export async function fetchTokens(projectSlug, meetingId, action, toastify) {
  try {
    const response = await axios.post(`/projects/${projectSlug}/meetings/${meetingId}/zoom-tokens`, {
      action,
    });
    return getObjectData(response);
  } catch (error) {
    toastify.error('Unable to generate meeting tokens');
    throw error;
  }
}

export async function setupAndJoinMeeting(action, meeting, jwt_token, zak_token, auth) {
  const client = ZoomMtgEmbedded.createClient();
  const meetingSDKElement = document.getElementById('meetingSDKElement');
  client.init({
    zoomAppRoot: meetingSDKElement,
    language: 'en-US',
    patchJsMedia: true,
    leaveOnPageUnload: true,
  });

  // Use correct env var for your build tool
  const sdkKey = import.meta.env.VITE_ZOOM_SDK_KEY;

  const meetingConfig = {
    sdkKey: sdkKey,
    signature: jwt_token,
    meetingNumber: meeting.meeting_id,
    password: meeting.password,
    userName: auth.name,
  };

  if (action === 'start') {
    meetingConfig.zak = zak_token;
  }

  await client.join(meetingConfig);
}
