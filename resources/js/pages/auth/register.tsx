import { useEffect, FormEventHandler, useState } from 'react';
import GuestLayout from '../../layouts/GuestLayout';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register({ referrer, referralCode }: { referrer?: { id: number; name: string; email: string }; referralCode?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        business_name: '',
        password: '',
        password_confirmation: '',
        referral_code: referralCode || null,
    });

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'));
    };

    return (
        <GuestLayout>
            <Head title="Register" />
            <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 px-4 py-8">
                <div className="w-full max-w-sm">
                    <div className="bg-white p-6 border border-gray-200">
                        <div className="text-center mb-6">
                            <img src='/prodataworld.jpg' alt="ProDataWorld" className="w-12 h-12 mx-auto mb-3" />
                            <h2 className="text-2xl font-bold text-gray-900">Create Account</h2>
                            {referrer && (
                                <div className="mt-3 p-3 bg-blue-50 border border-blue-200 rounded">
                                    <p className="text-sm text-blue-800">
                                        You were referred by: <span className="font-semibold">{referrer.name}</span>
                                    </p>
                                    <p className="text-xs text-blue-600">{referrer.email}</p>
                                </div>
                            )}
                        </div>
                        
                        <form onSubmit={submit} className="space-y-3">
                            {data.referral_code && (
                                <input type="hidden" name="referral_code" value={data.referral_code} />
                            )}
                            <div>
                                <Label htmlFor="name" className="text-sm text-gray-700 font-medium">Full Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    value={data.name}
                                    autoComplete="name"
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    className="mt-1 h-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-none"
                                />
                                {errors.name && <div className="text-red-600 text-xs mt-1">{errors.name}</div>}
                            </div>
                            
                            <div>
                                <Label htmlFor="business_name" className="text-sm text-gray-700 font-medium">Business Name</Label>
                                <Input
                                    id="business_name"
                                    name="business_name"
                                    value={data.business_name}
                                    autoComplete="organization"
                                    onChange={(e) => setData('business_name', e.target.value)}
                                    className="mt-1 h-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-none"
                                />
                                {errors.business_name && <div className="text-red-600 text-xs mt-1">{errors.business_name}</div>}
                            </div>
                            
                            <div>
                                <Label htmlFor="phone" className="text-sm text-gray-700 font-medium">Phone Number</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    value={data.phone}
                                    autoComplete="tel"
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className="mt-1 h-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-none"
                                />
                                {errors.phone && <div className="text-red-600 text-xs mt-1">{errors.phone}</div>}
                            </div>
                            
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
                                    className="mt-1 h-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-none"
                                />
                                {errors.email && <div className="text-red-600 text-xs mt-1">{errors.email}</div>}
                            </div>
                            
                            <div>
                                <Label htmlFor="password" className="text-sm text-gray-700 font-medium">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    autoComplete="new-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                    className="mt-1 h-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-none"
                                />
                                {errors.password && <div className="text-red-600 text-xs mt-1">{errors.password}</div>}
                            </div>
                            
                            <div>
                                <Label htmlFor="password_confirmation" className="text-sm text-gray-700 font-medium">Confirm Password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    value={data.password_confirmation}
                                    autoComplete="new-password"
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    required
                                    className="mt-1 h-10 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-none"
                                />
                                {errors.password_confirmation && <div className="text-red-600 text-xs mt-1">{errors.password_confirmation}</div>}
                            </div>
                            
                            <Button 
                                className="w-full h-10 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm mt-4 rounded-none" 
                                disabled={processing}
                            >
                                {processing ? 'Creating account...' : 'Create Account'}
                            </Button>
                        </form>
                        
                        <div className="mt-6 text-center">
                            <p className="text-sm text-gray-600">
                                Already have an account?{' '}
                                <Link
                                    href={route('login')}
                                    className="text-blue-600 hover:text-blue-500 font-medium"
                                >
                                    Sign in
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
