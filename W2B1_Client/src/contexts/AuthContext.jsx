import { createContext, useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router";
import BASE_URL from "../hooks/baseUrl";

const AuthContext = createContext({
    auth: null,
    user: null,
    updateProfile: () => {},
    logout: () => {},
});

const AuthContextProvider = ({ children }) => {
    const [token, setToken] = useState(localStorage.getItem("token"));
    const [profile, setProfile] = useState(() => {
        const savedProfile = localStorage.getItem("userProfile");
        return savedProfile ? JSON.parse(savedProfile) : null;
    });
    const navigate = useNavigate();

    const logout = () => {
        setToken(null);
        setProfile(null);
        navigate('/?type=all');
    };

    useEffect(() => {
        if (token) {
            localStorage.setItem("token", token);
        } else {
            localStorage.removeItem("token");
            localStorage.removeItem("userProfile");
        }
    }, [token]);

    useEffect(() => {
        if (!token) {
            return undefined;
        }

        const refreshProfile = () => {
            fetch(`${BASE_URL}/user`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                }
            })
                .then(res => {
                    if (res.status === 401) {
                        logout();
                        return Promise.reject('Unauthorized');
                    }
                    if (!res.ok) {
                        return Promise.reject('Failed to fetch profile');
                    }
                    return res.json();
                })
                .then(data => {
                    if (data?.data) {
                        setProfile(currentProfile => {
                            const updatedProfile = { ...currentProfile, ...data.data };
                            localStorage.setItem("userProfile", JSON.stringify(updatedProfile));
                            return updatedProfile;
                        });
                    }
                })
                .catch(error => {
                    if (error !== 'Unauthorized') {
                        console.error('Profile refresh failed:', error);
                    }
                });
        };

        refreshProfile();
        const interval = setInterval(refreshProfile, 10000);

        return () => clearInterval(interval);
    }, [token]);

    const updateProfile = (newProfile) => {
        setProfile(newProfile);
        if (newProfile) {
            localStorage.setItem("userProfile", JSON.stringify(newProfile));
        } else {
            localStorage.removeItem("userProfile");
        }
    };

    const value = useMemo(() => ({
        auth: token,
        user: profile,
        updateProfile,
        logout,
    }), [token, profile]);

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};

export { AuthContext, AuthContextProvider };
