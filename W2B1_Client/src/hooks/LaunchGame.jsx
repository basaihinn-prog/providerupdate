import BASE_URL from "./baseUrl";

const launchGame = (type_id, provider_id, game_id, room_id = null) => async (e) => {
  e.preventDefault();
  const auth = localStorage.getItem("token");
  if(!auth) {
    window.location.href = "/login";
  }
  
  const inputData = {
    type_id,
    provider_id,
    game_id,
    ...(room_id && { room_id }), // Include room_id if provided
  };

  try {
    // Check if this is a Buffalo game (provider_id === 23)
    const isBuffaloGame = provider_id === 23;
    const endpoint = isBuffaloGame ? '/buffalo/launch-game' : '/launch_game';
    
    const response = await fetch(`${BASE_URL}${endpoint}`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
      body: JSON.stringify(inputData),
    });

    if (!response.ok) {
      if(response.status === 401) {
        window.location.href = "/";
      }
      throw new Error("Launch Game failed");
    }

    const data = await response.json();
    // console.log(data);
    
    if (data.code === 1) {
      // Success - redirect to game
      window.location.href = data.Url || data.game_url;
      // window.open(data.Url || data.game_url, "_blank");
    } else {
      // Error from API
      console.error("Game launch error:", data.msg);
      alert(data.msg || "Failed to launch game");
    }
    
    // console.log("Launch Game success");
  } catch (error) {
    console.error("Launch Game error:", error);
    alert("Failed to launch game. Please try again.");
  }
};

export default launchGame;
