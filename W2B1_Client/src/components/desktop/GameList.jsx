import React, { useContext, useEffect, useMemo, useState } from 'react';
import { LanguageContext } from '../../contexts/LanguageContext';
import { GameContext } from '../../contexts/GameContext';
import BASE_URL from '../../hooks/baseUrl';
import RelatedProviderLists from './RelatedProviderLists.jsx';

export function GameList({ loading, games }) {
  const { content } = useContext(LanguageContext);
  const { current_type, current_provider } = useContext(GameContext);

  const [launchingGameId, setLaunchingGameId] = useState(null);
  const [launchError, setLaunchError] = useState('');
  const [page, setPage] = useState(1);
  const [allGames, setAllGames] = useState(games || []);
  const [hasMore, setHasMore] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  const customGameType = useMemo(() => {
    const map = {
      '1': 'SLOT',
      '2': 'LIVE_CASINO',
      '3': 'SPORT_BOOK',
      '4': 'VIRTUAL_SPORT',
      '5': 'LOTTERY',
      '8': 'FISHING',
      '12': 'POKER',
      '13': 'OTHERS',
    };
    return map[String(current_type)] || null;
  }, [current_type]);

  const fetchGames = async (pageNum, append = false) => {
    if (!current_type || !current_provider) return;

    setLoadingMore(true);
    try {
      const response = await fetch(
        `${BASE_URL}/game_lists/${current_type}/${current_provider}?page=${pageNum}`,
        {
          headers: {
            Authorization: `Bearer ${localStorage.getItem('token')}`,
            Accept: 'application/json',
          },
        }
      );

      if (response.status === 401) {
        localStorage.removeItem('token');
        localStorage.removeItem('userProfile');
        window.location.href = '/login';
        return;
      }

      const data = await response.json();
      const newGames = data?.data || [];

      setAllGames((previous) => {
        if (!append) return newGames;
        const existingIds = new Set(previous.map((game) => game.id));
        return [...previous, ...newGames.filter((game) => !existingIds.has(game.id))];
      });
      setHasMore(newGames.length === 20);
      setPage(pageNum);
    } catch (error) {
      console.error('Failed to fetch games:', error);
      setHasMore(false);
    } finally {
      setLoadingMore(false);
    }
  };

  useEffect(() => {
    if (games) {
      setAllGames(games);
      setHasMore(false);
      return;
    }

    setPage(1);
    setAllGames([]);
    setHasMore(true);
    if (current_type && current_provider) {
      fetchGames(1, false);
    }
  }, [games, current_type, current_provider]);

  const handleLoadMore = () => {
    if (!games && hasMore && !loadingMore) {
      fetchGames(page + 1, true);
    }
  };

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
      const response = await fetch(`${BASE_URL}/games/launch`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          game_id: game.id,
          language: 'my',
          platform: 'desktop',
        }),
      });

      if (response.status === 401) {
        localStorage.removeItem('token');
        localStorage.removeItem('userProfile');
        window.location.href = '/login';
        return;
      }

      const result = await response.json().catch(() => null);
      if (!response.ok || result?.code !== 200) {
        setLaunchError(result?.message || 'Failed to launch game.');
        return;
      }

      if (!result.url) {
        setLaunchError('Launch failed: provider returned no game URL.');
        return;
      }

      window.location.assign(result.url);
    } catch (error) {
      console.error('Desktop game launch failed:', error);
      setLaunchError('Network error. Please try again.');
    } finally {
      setLaunchingGameId(null);
    }
  };

  const displayGames = searchTerm
    ? allGames.filter((game) =>
        (game.game_name || '').toLowerCase().includes(searchTerm.toLowerCase())
      )
    : allGames;

  if (loading && displayGames.length === 0) {
    return (
      <div className='flex justify-center items-center py-8 w-full'>
        <div className='w-8 h-8 border-4 border-yellow-400 border-t-transparent rounded-full animate-spin' />
      </div>
    );
  }

  return (
    <>
      {launchError && (
        <div className='mb-3'>
          <div className='bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded relative text-center'>
            {launchError}
          </div>
        </div>
      )}

      <div className='scroll-container'>
        <RelatedProviderLists customGameType={customGameType} />
      </div>

      <div className='flex justify-end mb-3 custom-margin'>
        <input
          className='px-3 py-2 rounded-lg bg-[#23243a] text-white placeholder-gray-400 border border-yellow-400/60 focus:outline-none focus:border-yellow-400 focus:ring-2 focus:ring-yellow-400 w-full max-w-xs transition'
          placeholder='Search...'
          value={searchTerm}
          onChange={(event) => setSearchTerm(event.target.value)}
        />
      </div>

      {displayGames.length === 0 ? (
        <p className='text-center text-gray-400 py-6'>
          {content?.no_data || 'No games found.'}
        </p>
      ) : (
        <div className='grid grid-cols-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 w-full'>
          {displayGames.map((item) => (
            <div
              key={item.id}
              className='cursor-pointer group flex flex-col items-center'
              onClick={() => handleLaunchGame(item)}
            >
              <div className='relative w-full aspect-square overflow-hidden rounded-2xl bg-gradient-to-br from-yellow-400/70 via-white/10 to-yellow-600/70 p-1 group-hover:from-yellow-400 group-hover:to-orange-400 group-hover:shadow-2xl transition-all duration-200'>
                <div className='w-full h-full bg-black/60 rounded-2xl flex items-center justify-center group-hover:bg-black/80 transition-all duration-200'>
                  <img
                    src={item.image_url || item.img}
                    alt={item.game_name || 'Game'}
                    className='w-full h-full object-cover rounded-2xl group-hover:scale-105 transition-transform duration-200'
                  />
                  <button
                    className='absolute bottom-2 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full bg-gradient-to-r from-orange-400 to-yellow-500 text-white font-bold text-xs shadow hover:scale-105 transition whitespace-nowrap border border-yellow-300'
                    onClick={(event) => {
                      event.stopPropagation();
                      handleLaunchGame(item);
                    }}
                    disabled={launchingGameId === item.id}
                  >
                    {launchingGameId === item.id ? (
                      <span className='inline-block w-4 h-4 border-2 border-yellow-400 border-t-transparent rounded-full animate-spin align-middle' />
                    ) : (
                      content?.btn?.play_game || 'Play'
                    )}
                  </button>
                </div>
              </div>
              <div className='mt-2 flex justify-center w-full'>
                <span className='px-4 py-1 bg-black/80 text-yellow-300 font-bold text-xs rounded-full shadow text-center max-w-[90%] truncate whitespace-nowrap border border-yellow-300 group-hover:scale-105 transition-transform duration-200'>
                  {item.game_name}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}

      {hasMore && !games && (
        <div className='text-center my-3'>
          <button
            className='px-6 py-2 rounded-full bg-gradient-to-r from-yellow-400 to-orange-400 text-black font-bold text-sm shadow hover:scale-105 transition'
            style={{ maxWidth: 200 }}
            onClick={handleLoadMore}
            disabled={loadingMore}
          >
            {loadingMore ? (
              <span className='inline-block w-4 h-4 border-2 border-yellow-400 border-t-transparent rounded-full animate-spin align-middle' />
            ) : 'Load More'}
          </button>
        </div>
      )}

      <style>{`
        .scroll-container {
          display: flex;
          overflow-x: auto;
          gap: 0.5rem;
          padding-bottom: 2px;
          scrollbar-width: thin;
          scrollbar-color: #FFD700 #23272f;
        }
        .custom-margin { margin-top: 5px; }
        @media (max-width: 986px) {
          .custom-margin { margin-top: -13px; }
        }
      `}</style>
    </>
  );
}
