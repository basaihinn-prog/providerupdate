import React, { useState } from 'react';
import useFetch from '../../hooks/useFetch';
import BASE_URL from '../../hooks/baseUrl';
import Games from './Games';
import { Spinner } from 'react-bootstrap';

const AllTab = () => {
  const { data: types, loading } = useFetch(`${BASE_URL}/game_types`);
  const [launchingGameId, setLaunchingGameId] = useState(null);
  const [launchError, setLaunchError] = useState('');

  const handleLaunchGame = async (game) => {
    const token = localStorage.getItem('token');
    if (!token) {
      window.location.href = '/login';
      return;
    }

    if (!game?.id) {
      setLaunchError('Invalid game. Please refresh the game list.');
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
      console.error('Game launch failed:', error);
      setLaunchError('Network error. Please try again.');
    } finally {
      setLaunchingGameId(null);
    }
  };

  return (
    <div className='p-3'>
      <h5>All Games</h5>
      {launchError && (
        <div className='alert alert-danger' role='alert'>
          {launchError}
        </div>
      )}
      {loading && <Spinner />}
      {types?.map((type) => (
        <div key={type.id ?? type.code}>
          <h2 className='mb-3'>{type.name}</h2>
          <Games
            games={type.providers}
            type={type.name}
            onLaunchGame={handleLaunchGame}
            launchingGameId={launchingGameId}
          />
        </div>
      ))}
    </div>
  );
};

export default AllTab;
