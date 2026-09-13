import React, { useContext } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import GameHeading from '../components/mobile/GameHeading';
import GameLists from '../components/mobile/GameLists';
import ProviderList from '../components/mobile/ProviderList';
import HotTab from '../components/mobile/HotTab';
import useFetch from '../hooks/useFetch';
import BASE_URL from '../hooks/baseUrl';
import { LanguageContext } from '../contexts/LanguageContext';
import { Spinner } from 'react-bootstrap';
import shan from '../assets/img/shan.jpg';
import ponewine from '../assets/img/ponewine.jpg';
import { AuthContext } from '../contexts/AuthContext';
import { GameContext } from '../contexts/GameContext';

const GamesPage = () => {
    const { user } = useContext(AuthContext);
    const { content } = useContext(LanguageContext);
    const [searchParams] = useSearchParams();
    const type = searchParams.get('type');
    const tab = searchParams.get('tab');
    const provider = searchParams.get('provider');
    const { game_lists, provider_name, type_name, table_games, card_games, bingo_games } = useContext(GameContext);
    const { data: game_types, loading } = useFetch(`${BASE_URL}/game_types`);

    const handleLaunchGame = async (game) => {
        const token = localStorage.getItem('token');

        if (!token) {
            window.location.href = '/login';
            return;
        }

        if (!game?.id) {
            alert('Invalid game. Please refresh the game list.');
            return;
        }

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
                alert(result?.message || 'Failed to launch game.');
                return;
            }

            if (result.url) {
                window.location.assign(result.url);
                return;
            }

            if (result.content) {
                const gameWindow = window.open('', '_blank');
                if (gameWindow) {
                    gameWindow.document.write(result.content);
                    gameWindow.document.close();
                } else {
                    alert('Popup blocked. Please allow popups.');
                }
                return;
            }

            alert('Launch failed: provider returned no game URL.');
        } catch (error) {
            console.error('Game launch failed:', error);
            alert('Network error. Please try again.');
        }
    };

    const renderProviders = () => {
        if (tab !== 'all') {
            return null;
        }

        return (
            <>
                <div className='mb-4'>
                    <h5 className='mb-3'>{content?.game_type?.burmese}</h5>
                    <div className='d-flex align-items-center gap-3'>
                        <Link to={`https://goldendragon7.pro/?user_name=${user?.user_name}&balance=${user?.balance}`} target='_blank'>
                            <img src={shan} width={100} className='rounded' alt='Shan game' />
                        </Link>
                        <Link to={`https://ponewine20x.netlify.app/?user_name=${user?.user_name}&balance=${user?.balance}`} target='_blank'>
                            <img src={ponewine} width={100} className='rounded' alt='Ponewine game' />
                        </Link>
                    </div>
                </div>
                {game_types?.map((item) => (
                    <div key={item.id ?? item.code} className='mb-4'>
                        <h5 className='mb-4'>{item.name}</h5>
                        <ProviderList
                            loading={loading}
                            providers={item.providers}
                            type={item.code}
                        />
                    </div>
                ))}
            </>
        );
    };

    const renderGameList = (games, topSpacing = 'mt-2') => {
        if (loading) {
            return <Spinner />;
        }

        if (!games?.length) {
            return <h5 className='text-center'>{content?.no_data}</h5>;
        }

        return games.map((item) => (
            <div
                key={item.id ?? `${item.game_code}-${item.product_id}`}
                className={`p-0 ${topSpacing} cursor-pointer col-4 col-sm-3 col-lg-2 mb-2 mb-sm-3`}
                onClick={() => handleLaunchGame(item)}
                role='button'
                tabIndex={0}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        handleLaunchGame(item);
                    }
                }}
            >
                <img src={item.img || item.image_url} className='gameImg rounded-3' alt={item.game_name || 'Game'} />
            </div>
        ));
    };

    return (
        <div style={{ overflowX: 'hidden' }}>
            <GameHeading />
            <GameLists />
            <div>
                <div className='d-flex justify-content-between px-2'>
                    <h6 className='fw-bold text-white ms-2'>
                        {type !== 'all' && type_name}
                    </h6>
                    <h6>{provider && provider !== 'undefined' ? provider_name : ''}</h6>
                </div>
                <div className='row mb-5 px-4 pb-5'>
                    {tab === 'all' && renderProviders()}
                    {type && type !== 'all' && renderGameList(game_lists)}
                    {tab === 'hot' && <HotTab />}
                    {tab === 'table' && (
                        <>
                            <h5>Table Games</h5>
                            {renderGameList(table_games, 'mt-4')}
                        </>
                    )}
                    {tab === 'card' && (
                        <>
                            <h5>Card Games</h5>
                            {renderGameList(card_games, 'mt-4')}
                        </>
                    )}
                    {tab === 'bingo' && (
                        <>
                            <h5>Bingo Games</h5>
                            {renderGameList(bingo_games, 'mt-4')}
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};

export default GamesPage;
