import '../css/app.css';
import '@fontsource-variable/manrope';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './auth';
import { DotsProvider } from './dots';
import ErrorBoundary from './components/ErrorBoundary';
import Preloader from './components/Preloader';
import Layout from './components/Layout';
import Home from './pages/Home';
import Villas from './pages/Villas';
import VillaDetail from './pages/VillaDetail';
import Login from './pages/Login';
import Bookings from './pages/Bookings';
import Host from './pages/Host';
import Admin from './pages/Admin';
import Account from './pages/Account';
import { Fragment } from 'react';
import { setCurrency } from './money';
import About from './pages/About';


function Protected({ roles, children }) {
    const { user, ready } = useAuth();
    if (!ready) return null;
    if (!user) return <Navigate to="/login" replace />;
    if (roles && !roles.includes(user.role)) return <Navigate to="/" replace />;
    return children;
}

// Quand la monnaie du compte change, l'affichage est remonté : tous les prix se recalculent
function MoneyBoundary({ children }) {
    const { user } = useAuth();
    const currency = user?.currency === 'USD' ? 'USD' : 'MGA';
    setCurrency(currency);
    return <Fragment key={currency}>{children}</Fragment>;
}

// Une seule racine React, même si ce fichier est ré-exécuté par le rechargement à chaud de Vite
const container = document.getElementById('root');
const root = (container._reactRoot ??= createRoot(container));

root.render(
    <ErrorBoundary>
        <BrowserRouter>
            <AuthProvider>
                <DotsProvider>
                <Preloader />
                <MoneyBoundary>
                    <Routes>
                    <Route element={<Layout />}>
                        <Route path="/" element={<Home />} />
                        <Route path="/villas" element={<Villas />} />
                        <Route path="/villas/:id" element={<VillaDetail />} />
                        <Route path="/login" element={<Login />} />  
                        <Route path="/bookings" element={<Protected><Bookings /></Protected>} />
                        <Route path="/account" element={<Protected roles={['client', 'host']}><Account /></Protected>} />
                        <Route path="/host" element={<Protected roles={['host']}><Host /></Protected>} />
                        <Route path="/admin" element={<Protected roles={['admin']}><Admin /></Protected>} />  
                        <Route path='/about' element={<About />} />   
                    </Route>
                </Routes>
                </MoneyBoundary>
                
                </DotsProvider>
            </AuthProvider>
        </BrowserRouter>
    </ErrorBoundary>
);