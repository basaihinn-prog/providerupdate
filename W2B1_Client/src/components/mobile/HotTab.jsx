import React, { useContext, useState } from 'react';
import { Spinner } from 'react-bootstrap';
import { GameContext } from '../../contexts/GameContext';
import BASE_URL from '../../hooks/baseUrl';

const HotTab = () => {
  const { hot_games, loading } = useContext(GameContext);
  const [launchingGameId, setLaunchingGameId] = useState(null);
  const [launchError, setLaunchError] = useState('');

  const handleLaunchGame = async (game) => {
    const token = localStorage.getItem('token');
    if (!token) {
      window.location.href = '/login';
      return;
    }

    setLaunchingGameId(game.id);
    setLaunchError('');

    try {
      const res = await fetch(`${BASE_URL}/games/launch`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          game_id: game.id,
          language: 'my',
          platform: window.innerWidth < 768 ? 'mobile' : 'desktop',
        }),
      });

      if (res.status === 401) {
        localStorage.removeItem('token');
        localStorage.removeItem('userProfile');
        window.location.href = '/login';
        return;
      }

      const result = await res.json().catch(() => null);

      if (!res.ok || result?.code !== 200) {
        setLaunchError(result?.message || 'Failed to launch game.');
        return;
      }

      if (result.url) {
        window.location.assign(result.url);
      } else {
        setLaunchError('Launch failed: provider returned no game URL.');
      }
    } catch (error) {
      console.error('Hot game launch failed:', error);
      setLaunchError('Network error. Please try again.');
    } finally {
      setLaunchingGameId(null);
    }
  };

  return (
    <div className='container-fluid mb-5'>
      <h2 className='my-3'>Hot Games</h2>
      {launchError && (
        <div className='alert alert-danger' role='alert'>
          {launchError}
        </div>
      )}
      <div className='row mx-1 mb-5 pb-5'>
        {loading ? <Spinner /> :
        hot_games?.length > 0 ? hot_games.map((item) => (
        <div
          key={item.id}
          className='p-0 cursor-pointer col-4 col-md-2 mb-3 position-relative'
          onClick={() => handleLaunchGame(item)}
        >
          <img
            src={item.image_url || item.img}
            alt={item.game_name || 'Game'}
            className='rounded-3 hotgame'
            style={{
              opacity: launchingGameId === item.id ? 0.6 : 1,
              pointerEvents: launchingGameId === item.id ? 'none' : 'auto'
            }}
          />
          {launchingGameId === item.id && (
            <div className='position-absolute top-50 start-50 translate-middle'>
              <Spinner size='sm' />
            </div>
          )}
        </div>
        )) : <h4 className='text-center'>There is no game list.</h4>}
      </div>
    </div>
  );
};

export default HotTab;
