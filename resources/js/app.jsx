import '../css/app.css';
import '@fontsource-variable/manrope';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './auth';
import Layout from './components/Layout';
import Home from './pages/Home';
import Villas from './pages/Villas';
import VillaDetail from './pages/VillaDetail';
import Login from './pages/Login';
import Bookings from './pages/Bookings';
import Host from './pages/Host';
import Admin from './pages/Admin';

function Protected({ roles, children }) {
    const { user, ready } = useAuth();
    if (!ready) return null;
    if (!user) return <Navigate to="/login" replace />;
    if (roles && !roles.includes(user.role)) return <Navigate to="/" replace />;
    return children;
}

createRoot(document.getElementById('root')).render(
    <BrowserRouter>
        <AuthProvider>
            <Routes>
                <Route element={<Layout />}>
                    <Route path="/" element={<Home />} />
                    <Route path="/villas" element={<Villas />} />
                    <Route path="/villas/:id" element={<VillaDetail />} />
                    <Route path="/login" element={<Login />} />
                    <Route path="/bookings" element={<Protected><Bookings /></Protected>} />
                    <Route path="/host" element={<Protected roles={['host', 'admin']}><Host /></Protected>} />
                    <Route path="/admin" element={<Protected roles={['admin']}><Admin /></Protected>} />
                </Route>
            </Routes>
        </AuthProvider>
    </BrowserRouter>
);
