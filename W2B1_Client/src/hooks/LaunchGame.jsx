import BASE_URL from './baseUrl';

const launchGame = (type_id, provider_id, game_id, room_id = null) => async (event) => {
  event?.preventDefault?.();

  const token = localStorage.getItem('token');
  if (!token) {
    window.location.href = '/login';
    return;
  }

  try {
    // Buffalo remains a first-party/local game flow. All provider-backed games
    // use the canonical provider-agnostic Laravel launch endpoint.
    const isBuffaloGame = provider_id === 23;
    const endpoint = isBuffaloGame ? '/buffalo/launch-game' : '/games/launch';
    const payload = isBuffaloGame
      ? {
          type_id,
          provider_id,
          game_id,
          ...(room_id && { room_id }),
        }
      : {
          game_id,
          language: 'my',
          platform: window.innerWidth < 768 ? 'mobile' : 'desktop',
        };

    const response = await fetch(`${BASE_URL}${endpoint}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify(payload),
    });

    if (response.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('userProfile');
      window.location.href = '/login';
      return;
    }

    const data = await response.json().catch(() => null);

    if (!response.ok) {
      throw new Error(data?.message || data?.msg || 'Launch game failed');
    }

    if (isBuffaloGame) {
      const url = data?.Url || data?.game_url || data?.url;
      if (!url) {
        throw new Error(data?.msg || 'Buffalo launch URL missing');
      }
      window.location.assign(url);
      return;
    }

    if (data?.code !== 200 || !data?.url) {
      throw new Error(data?.message || 'Provider launch URL missing');
    }

    window.location.assign(data.url);
  } catch (error) {
    console.error('Launch Game error:', error);
    alert(error.message || 'Failed to launch game. Please try again.');
  }
};

export default launchGame;
