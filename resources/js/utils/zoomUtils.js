import axios from 'axios';
import ZoomMtgEmbedded from '@zoom/meetingsdk/embedded';

export async function getToken(url, errorMessage, toastify) {
  try {
    const response = await axios.get(url);
    return response.data && response.data.data ? response.data.data : response.data;
  } catch (error) {
    toastify.error(errorMessage);
    throw error;
  }
}

export async function fetchTokens(action, role, meetingId, toastify) {
  return await Promise.all([
    action === 'start'
      ? getToken('/users/me/zoom-token', 'Unable to generate ZAK token', toastify)
      : Promise.resolve(null),
    getToken(`/users/me/zoom-jwt-token?role=${role}&meetingId=${meetingId}`, 'Unable to generate JWT token', toastify),
  ]);
}

export async function setupAndJoinMeeting(action, meeting, jwt_token, zak_token, auth, toastify) {
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

  try {
    await client.join(meetingConfig);
  } catch (err) {
    if (toastify) toastify.error('Failed to join meeting. Please try again.');
    throw err;
  }
}
