import { useEffect, FormEventHandler, useState } from 'react';
import GuestLayout from '@/layouts/GuestLayout';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });
    const [showPassword, setShowPassword] = useState(false);

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
            <div className="flex flex-col items-center justify-center min-h-[70vh]">
                <div className="w-full max-w-md bg-white/60 dark:bg-gray-800/70 backdrop-blur-lg rounded-3xl shadow-2xl p-10 border border-gray-200 dark:border-gray-700 relative overflow-hidden">
                    <div className="flex flex-col items-center mb-8">
                        <img src='/prodataworld.jpg' alt="Dataworld Logo" className="w-20 h-20 mb-4 mx-auto rounded-3xl" />
                        <h2 className="text-3xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight mb-1">Sign In</h2>
                        <p className="text-gray-500 dark:text-gray-400 text-base">Access your account</p>
                    </div>
                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <Label htmlFor="email">Email</Label>
                            <div className="relative mt-1">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" strokeWidth="2" d="M4 4h16v16H4V4zm0 0l8 8 8-8"/></svg>
                                </span>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    autoComplete="username"
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                    autoFocus
                                    aria-invalid={!!errors.email}
                                    className="pl-10"
                                />
                            </div>
                            {errors.email && <div className="text-red-500 text-xs mt-1">{errors.email}</div>}
                        </div>
                        <div>
                            <Label htmlFor="password">Password</Label>
                            <div className="relative mt-1">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" strokeWidth="2" d="M12 17a2 2 0 100-4 2 2 0 000 4zm6-2V9a6 6 0 10-12 0v6a6 6 0 0012 0z"/></svg>
                                </span>
                                <Input
                                    id="password"
                                    type={showPassword ? "text" : "password"}
                                    name="password"
                                    value={data.password}
                                    autoComplete="current-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                    aria-invalid={!!errors.password}
                                    className="pl-10 pr-10"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    {showPassword ? (
                                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/></svg>
                                    ) : (
                                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke="currentColor" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && <div className="text-red-500 text-xs mt-1">{errors.password}</div>}
                        </div>
                        <div className="flex items-center justify-between">
                            <label className="flex items-center gap-2">
                                <Input
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                />
                                <span className="text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                            </label>
                            <Link
                                href={route('password.request')}
                                className="text-sm text-indigo-600 dark:text-indigo-400 hover:underline focus:outline-none"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <Button className="w-full h-12 text-lg font-bold rounded-2xl shadow-lg bg-gradient-to-r from-indigo-500 via-blue-500 to-purple-500 hover:from-indigo-600 hover:to-blue-600 transition-all duration-200" disabled={processing}>
                            Log in
                        </Button>
                    </form>
                    <div className="flex items-center my-8">
                        <div className="flex-grow border-t border-gray-300 dark:border-gray-600"></div>
                        <span className="mx-4 text-gray-400 text-sm">or</span>
                        <div className="flex-grow border-t border-gray-300 dark:border-gray-600"></div>
                    </div>
                    <div className="flex flex-col gap-2">
                        <Link
                            href={route('register')}
                            className="w-full inline-block text-center py-3 px-4 rounded-2xl font-semibold bg-white/80 dark:bg-gray-900/80 border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 shadow hover:bg-indigo-50 dark:hover:bg-indigo-800 transition-all duration-150"
                        >
                            Create an account
                        </Link>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
