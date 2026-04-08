import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const [scrolled, setScrolled] = useState(false);
    const [navOpen, setNavOpen] = useState(false);

    useEffect(() => {
        const handleScroll = () => {
            setScrolled(window.scrollY > 100);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => {
        const handleResize = () => {
            if (window.innerWidth >= 1024) setNavOpen(false);
        };
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    return (
        <>
            <Head title="ProDataWorld - Premium Data Services">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />
            </Head>
            
            <div className="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900">
                {/* Navigation */}
                <nav className={`fixed top-0 w-full z-50 transition-all duration-300 ${
                    scrolled 
                        ? 'bg-slate-900/95 backdrop-blur-xl border-b border-slate-700/50' 
                        : 'bg-transparent'
                }`}>
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <div className="flex justify-between items-center py-6">
                            <div className="flex items-center space-x-3">
                                <img src='/prodataworld.jpg' alt="ProDataWorld" className="w-12 h-12" />
                                <span className="text-2xl font-bold text-white">ProDataWorld</span>
                            </div>
                            
                            <button
                                className="lg:hidden text-white p-2"
                                onClick={() => setNavOpen(!navOpen)}
                            >
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            
                            <div className="hidden lg:flex space-x-6">
                                {auth.user ? (
                                    <Link
                                        href={auth.user.role === 'admin' ? route('admin.dashboard') : route('dashboard')}
                                        className="px-8 py-3 bg-blue-600 text-white font-semibold hover:bg-blue-700 transition-colors duration-200"
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={route('register')}
                                            className="px-8 py-3 bg-blue-600 text-white font-semibold hover:bg-blue-700 transition-colors duration-200"
                                        >
                                            Get Started
                                        </Link>
                                        <Link
                                            href={route('login')}
                                            className="px-8 py-3 border border-white text-white font-semibold hover:bg-white hover:text-slate-900 transition-colors duration-200"
                                        >
                                            Login
                                        </Link>
                                    </>
                                )}
                            </div>
                        </div>
                        
                        {navOpen && (
                            <div className="lg:hidden pb-6">
                                <div className="flex flex-col space-y-4">
                                    {auth.user ? (
                                        <Link
                                            href={auth.user.role === 'admin' ? route('admin.dashboard') : route('dashboard')}
                                            className="px-8 py-3 bg-blue-600 text-white font-semibold text-center hover:bg-blue-700 transition-colors duration-200"
                                            onClick={() => setNavOpen(false)}
                                        >
                                            Dashboard
                                        </Link>
                                    ) : (
                                        <>
                                            <Link
                                                href={route('register')}
                                                className="px-8 py-3 bg-blue-600 text-white font-semibold text-center hover:bg-blue-700 transition-colors duration-200"
                                                onClick={() => setNavOpen(false)}
                                            >
                                                Get Started
                                            </Link>
                                            <Link
                                                href={route('login')}
                                                className="px-8 py-3 border border-white text-white font-semibold text-center hover:bg-white hover:text-slate-900 transition-colors duration-200"
                                                onClick={() => setNavOpen(false)}
                                            >
                                                Login
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </nav>

                {/* Hero Section */}
                <section className="min-h-screen flex items-center justify-center px-6 lg:px-8 relative">
                    <div className="absolute inset-0 bg-gradient-to-r from-blue-600/20 to-purple-600/20"></div>
                    
                    <div className="max-w-5xl mx-auto text-center relative z-10 pt-20">
                        <h1 className="text-5xl lg:text-7xl font-bold text-white mb-8 leading-tight">
                            Premium Data Services
                            <span className="block text-blue-400">Made Simple</span>
                        </h1>
                        
                        <p className="text-xl lg:text-2xl text-slate-300 mb-12 max-w-3xl mx-auto leading-relaxed">
                            Get reliable, affordable data bundles and become a reseller. 
                            Join thousands of satisfied customers across Ghana.
                        </p>
                        
                        <div className="flex flex-col sm:flex-row gap-6 justify-center">
                            {auth.user ? (
                                <Link
                                    href={auth.user.role === 'admin' ? route('admin.dashboard') : route('dashboard')}
                                    className="px-12 py-4 bg-blue-600 text-white font-bold text-lg hover:bg-blue-700 transition-colors duration-200"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('register')}
                                        className="px-12 py-4 bg-blue-600 text-white font-bold text-lg hover:bg-blue-700 transition-colors duration-200"
                                    >
                                        Start Today
                                    </Link>
                                    <Link
                                        href={route('login')}
                                        className="px-12 py-4 border-2 border-white text-white font-bold text-lg hover:bg-white hover:text-slate-900 transition-colors duration-200"
                                    >
                                        Login
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section className="py-24 bg-slate-800">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-4xl font-bold text-white mb-4">Why Choose ProDataWorld?</h2>
                            <p className="text-xl text-slate-300">Experience the difference with our premium services</p>
                        </div>
                        
                        <div className="grid md:grid-cols-3 gap-8">
                            <div className="bg-slate-700 p-8 text-center">
                                <div className="w-16 h-16 bg-blue-600 mx-auto mb-6 flex items-center justify-center">
                                    <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <h3 className="text-2xl font-bold text-white mb-4">Instant Delivery</h3>
                                <p className="text-slate-300">Get your data bundles delivered instantly to any network in Ghana</p>
                            </div>
                            
                            <div className="bg-slate-700 p-8 text-center">
                                <div className="w-16 h-16 bg-green-600 mx-auto mb-6 flex items-center justify-center">
                                    <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                    </svg>
                                </div>
                                <h3 className="text-2xl font-bold text-white mb-4">Best Prices</h3>
                                <p className="text-slate-300">Competitive rates that help you save money and earn more as a reseller</p>
                            </div>
                            
                            <div className="bg-slate-700 p-8 text-center">
                                <div className="w-16 h-16 bg-purple-600 mx-auto mb-6 flex items-center justify-center">
                                    <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 className="text-2xl font-bold text-white mb-4">24/7 Support</h3>
                                <p className="text-slate-300">Round-the-clock customer support to help you whenever you need assistance</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="py-24 bg-gradient-to-r from-blue-600 to-purple-600">
                    <div className="max-w-4xl mx-auto text-center px-6 lg:px-8">
                        <h2 className="text-4xl lg:text-5xl font-bold text-white mb-8">
                            Ready to Get Started?
                        </h2>
                        <p className="text-xl text-blue-100 mb-12">
                            Join thousands of satisfied customers and start your data business today
                        </p>
                        {!auth.user && (
                            <Link
                                href={route('register')}
                                className="inline-block px-12 py-4 bg-white text-blue-600 font-bold text-lg hover:bg-gray-100 transition-colors duration-200"
                            >
                                Create Account Now
                            </Link>
                        )}
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-slate-900 py-12">
                    <div className="max-w-7xl mx-auto px-6 lg:px-8">
                        <div className="flex flex-col md:flex-row justify-between items-center">
                            <div className="flex items-center space-x-3 mb-4 md:mb-0">
                                <img src='/prodataworld.jpg' alt="ProDataWorld" className="w-10 h-10" />
                                <span className="text-xl font-bold text-white">ProDataWorld</span>
                            </div>
                            <div className="text-slate-400">
                                <p>&copy; 2024 ProDataWorld. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}