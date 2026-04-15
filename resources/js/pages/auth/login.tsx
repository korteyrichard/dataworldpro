import { useEffect, FormEventHandler, useState } from 'react';
import GuestLayout from '@/layouts/GuestLayout';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <GuestLayout>
            <Head title="Log in" />
            <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 px-4">
                <div className="w-full max-w-sm">
                    <div className="bg-white p-6 border border-gray-200">
                        <div className="text-center mb-6">
                            <img src='/prodataworld.jpg' alt="ProDataWorld" className="w-12 h-12 mx-auto mb-3" />
                            <h2 className="text-2xl font-bold text-gray-900">Sign In</h2>
                        </div>
                        
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="email" className="text-sm text-gray-700 font-medium">Email Address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    autoComplete="username"
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                    autoFocus
                                    className="mt-1 h-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-none"
                                />
                                {errors.email && <div className="text-red-600 text-xs mt-1">{errors.email}</div>}
                            </div>
                            
                            <div>
                                <Label htmlFor="password" className="text-sm text-gray-700 font-medium">Password</Label>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={showPassword ? "text" : "password"}
                                        name="password"
                                        value={data.password}
                                        autoComplete="current-password"
                                        onChange={(e) => setData('password', e.target.value)}
                                        required
                                        className="mt-1 h-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-none pr-10"
                                    />
                                    <button
                                        type="button"
                                        className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                                        onClick={() => setShowPassword(!showPassword)}
                                    >
                                        {showPassword ? (
                                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                            </svg>
                                        ) : (
                                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        )}
                                    </button>
                                </div>
                                {errors.password && <div className="text-red-600 text-xs mt-1">{errors.password}</div>}
                            </div>
                            
                            <div className="flex items-center justify-between pt-1">
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                    />
                                    <span className="ml-2 text-xs text-gray-600">Remember me</span>
                                </label>
                                <Link
                                    href={route('password.request')}
                                    className="text-xs text-blue-600 hover:text-blue-500"
                                >
                                    Forgot password?
                                </Link>
                            </div>
                            
                            <Button 
                                className="w-full h-10 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm mt-4 rounded-none" 
                                disabled={processing}
                            >
                                {processing ? 'Signing in...' : 'Sign In'}
                            </Button>
                        </form>
                        
                        <div className="mt-6 text-center">
                            <p className="text-sm text-gray-600">
                                Don't have an account?{' '}
                                <Link
                                    href={route('register')}
                                    className="text-blue-600 hover:text-blue-500 font-medium"
                                >
                                    Create account
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
