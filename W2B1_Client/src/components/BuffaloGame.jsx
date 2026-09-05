import React, { useContext, useState } from "react";
import { AuthContext } from "../contexts/AuthContext";
import buffaloLogo from "../assets/buffalo/500-200x200.png";
import BASE_URL from "../hooks/baseUrl";

const BuffaloGame = () => {
  const { user } = useContext(AuthContext);
  const [selectedRoom, setSelectedRoom] = useState(null);

  const handleLaunchGame = async (roomId) => {
    if (!user) {
      alert("Please login to play Buffalo game");
      return;
    }

    try {
      // Get the game URL directly and navigate to it
      const response = await fetch(`${BASE_URL}/buffalo/launch-game`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify({
          type_id: 1,
          provider_id: 23,
          game_id: 23,
          room_id: roomId,
        }),
      });

      if (!response.ok) {
        throw new Error("Launch Game failed");
      }

      const data = await response.json();
      
      if (data.code === 1) {
        // Direct navigation to game URL (no iframe)
        window.location.href = data.Url || data.game_url;
      } else {
        alert(data.msg || "Failed to launch game");
      }
    } catch (error) {
      console.error("Launch Game error:", error);
      alert("Failed to launch game. Please try again.");
    }
  };

  const handleBackToRooms = () => {
    setSelectedRoom(null);
  };

  const rooms = [
    {
      id: 1,
      name: "50 အခန်း",
      minBet: 50,
      level: "Basic",
      description: "Perfect for beginners"
    },
    {
      id: 2,
      name: "500 အခန်း",
      minBet: 500,
      level: "Intermediate",
      description: "For experienced players"
    },
    {
      id: 3,
      name: "5000 အခန်း",
      minBet: 5000,
      level: "High",
      description: "High stakes gaming"
    },
    {
      id: 4,
      name: "10000 အခန်း",
      minBet: 10000,
      level: "VIP",
      description: "Exclusive VIP room"
    }
  ];

  const canPlayRoom = (minBet) => {
    if (!user) return false;
    return user.balance >= minBet;
  };

  // No longer need the room component since we're using direct navigation
  // if (selectedRoom) {
  //   return (
  //     <BuffaloGameRoom 
  //       roomId={selectedRoom} 
  //       onBack={handleBackToRooms}
  //     />
  //   );
  // }

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <div className="text-center mb-8">
          <img 
            src={buffaloLogo} 
            alt="Buffalo Game" 
            className="w-40 h-40 mx-auto mb-6 rounded-3xl shadow-2xl border-4 border-yellow-400/30"
          />
          <h1 className="text-4xl font-bold text-yellow-400 mb-4">
            Buffalo Slot Game
          </h1>
          <p className="text-xl text-gray-300 max-w-2xl mx-auto">
            Experience the thrill of the African savanna with our exciting Buffalo slot game. 
            Choose your room and start your adventure!
          </p>
        </div>

        {/* User Balance */}
        {user && (
          <div className="bg-gradient-to-r from-yellow-400/20 to-orange-500/20 rounded-2xl p-4 mb-8 border border-yellow-400/30">
            <div className="flex items-center justify-center space-x-4">
              <div className="text-center">
                <p className="text-gray-300 text-sm">Your Balance</p>
                <p className="text-2xl font-bold text-yellow-400">
                  {user.balance?.toLocaleString() || 0}
                </p>
              </div>
            </div>
            <div className="mt-3 text-center">
              <p className="text-gray-300 text-sm">
                Available Rooms: {rooms.filter(room => canPlayRoom(room.minBet)).length} of {rooms.length}
              </p>
            </div>
          </div>
        )}

        {/* Room Selection */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {rooms.map((room) => (
            <div 
              key={room.id}
              className={`relative bg-gradient-to-br from-slate-800/80 to-slate-900/80 rounded-2xl p-6 border-2 transition-all duration-300 hover:scale-105 ${
                canPlayRoom(room.minBet) 
                  ? 'border-yellow-400/50 hover:border-yellow-400 hover:shadow-2xl hover:shadow-yellow-400/20' 
                  : 'border-gray-600/50 opacity-60'
              }`}
            >
              {/* Room Badge */}
              <div className="absolute -top-3 -right-3">
                <div className={`px-3 py-1 rounded-full text-xs font-bold ${
                  room.level === 'Basic' ? 'bg-green-500 text-white' :
                  room.level === 'Intermediate' ? 'bg-blue-500 text-white' :
                  room.level === 'High' ? 'bg-orange-500 text-white' :
                  'bg-purple-500 text-white'
                }`}>
                  {room.level}
                </div>
              </div>

              {/* Availability Indicator */}
              {!canPlayRoom(room.minBet) && (
                <div className="absolute top-2 left-2">
                  <div className="bg-red-500 text-white px-2 py-1 rounded-full text-xs font-bold">
                    Insufficient Balance
                  </div>
                </div>
              )}

              {/* Room Icon */}
              <div className="text-center mb-4">
                <div className={`w-20 h-20 mx-auto rounded-full flex items-center justify-center mb-4 ${
                  canPlayRoom(room.minBet)
                    ? 'bg-gradient-to-br from-yellow-400 to-orange-500'
                    : 'bg-gradient-to-br from-gray-500 to-gray-600'
                }`}>
                  <span className={`text-2xl font-bold ${
                    canPlayRoom(room.minBet) ? 'text-black' : 'text-gray-300'
                  }`}>
                    {room.minBet >= 10000 ? '10K' : 
                     room.minBet >= 5000 ? '5K' : 
                     room.minBet >= 500 ? '500' : '50'}
                  </span>
                </div>
                <h3 className="text-xl font-bold text-yellow-400 mb-2">
                  {room.name}
                </h3>
                <p className="text-gray-300 text-sm mb-1">
                  Min Bet: {room.minBet.toLocaleString()}
                </p>
                <p className="text-gray-400 text-xs">
                  {room.description}
                </p>
              </div>

              {/* Play Button */}
              <button
                onClick={() => handleLaunchGame(room.id)}
                disabled={!canPlayRoom(room.minBet)}
                className={`w-full py-3 rounded-xl font-bold transition-all duration-300 ${
                  canPlayRoom(room.minBet)
                    ? 'bg-gradient-to-r from-yellow-400 to-orange-500 text-black hover:from-yellow-300 hover:to-orange-400 hover:shadow-lg'
                    : 'bg-gray-600 text-gray-400 cursor-not-allowed'
                }`}
              >
                {!user ? 'Login to Play' : 
                 !canPlayRoom(room.minBet) ? 'Insufficient Balance' : 
                 '🚀 Launch Game'}
              </button>
            </div>
          ))}
        </div>

        {/* Game Features */}
        <div className="bg-gradient-to-br from-slate-800/50 to-slate-900/50 rounded-2xl p-8 border border-yellow-400/20">
          <h2 className="text-2xl font-bold text-yellow-400 text-center mb-8">
            Game Features
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div className="text-center">
              <div className="w-16 h-16 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full mx-auto mb-4 flex items-center justify-center">
                <span className="text-black font-bold text-2xl">🎰</span>
              </div>
              <h3 className="text-xl font-bold text-yellow-400 mb-2">1024 Ways to Win</h3>
              <p className="text-gray-300">
                Multiple winning combinations with exciting payouts
              </p>
            </div>
            
            <div className="text-center">
              <div className="w-16 h-16 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full mx-auto mb-4 flex items-center justify-center">
                <span className="text-black font-bold text-2xl">🐃</span>
              </div>
              <h3 className="text-xl font-bold text-yellow-400 mb-2">Wild Animals</h3>
              <p className="text-gray-300">
                Buffalo, Lion, Elephant, and Zebra symbols bring the savanna to life
              </p>
            </div>
            
            <div className="text-center">
              <div className="w-16 h-16 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full mx-auto mb-4 flex items-center justify-center">
                <span className="text-black font-bold text-2xl">🏆</span>
              </div>
              <h3 className="text-xl font-bold text-yellow-400 mb-2">High RTP</h3>
              <p className="text-gray-300">
                Excellent return to player rate for fair gaming experience
              </p>
            </div>
          </div>
        </div>

        {/* How to Play */}
        <div className="mt-8 bg-gradient-to-br from-slate-800/50 to-slate-900/50 rounded-2xl p-8 border border-yellow-400/20">
          <h2 className="text-2xl font-bold text-yellow-400 text-center mb-6">
            How to Play
          </h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div className="text-center">
              <div className="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full mx-auto mb-3 flex items-center justify-center">
                <span className="text-black font-bold">1</span>
              </div>
              <h4 className="text-yellow-400 font-bold mb-2">Choose Room</h4>
              <p className="text-gray-300 text-sm">Select a room based on your balance</p>
            </div>
            
            <div className="text-center">
              <div className="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full mx-auto mb-3 flex items-center justify-center">
                <span className="text-black font-bold">2</span>
              </div>
              <h4 className="text-yellow-400 font-bold mb-2">Set Bet</h4>
              <p className="text-gray-300 text-sm">Choose your bet amount</p>
            </div>
            
            <div className="text-center">
              <div className="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full mx-auto mb-3 flex items-center justify-center">
                <span className="text-black font-bold">3</span>
              </div>
              <h4 className="text-yellow-400 font-bold mb-2">Spin Reels</h4>
              <p className="text-gray-300 text-sm">Hit the spin button to play</p>
            </div>
            
            <div className="text-center">
              <div className="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full mx-auto mb-3 flex items-center justify-center">
                <span className="text-black font-bold">4</span>
              </div>
              <h4 className="text-yellow-400 font-bold mb-2">Win Big</h4>
              <p className="text-gray-300 text-sm">Match symbols to win prizes</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BuffaloGame;
