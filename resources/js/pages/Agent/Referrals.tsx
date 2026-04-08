import React from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

interface Referral {
    id: number;
    referred_user: { 
        id: number;
        name: string; 
        email: string;
        role: string;
        created_at: string;
    };
    referred_at: string;
    created_at: string;
}

interface ReferralCommission {
    id: number;
    referral_amount: number;
    referral_percentage: number;
    status: string;
    referred_agent: { 
        id: number;
        name: string;
        email: string;
    };
    available_at: string;
    created_at: string;
}

interface ReferralsProps {
    auth: { user: any };
    referrals: Referral[];
    referralCommissions: ReferralCommission[];
    referralCode: string;
    referralUrl: string;
}

export default function Referrals({ auth, referrals, referralCommissions, referralCode, referralUrl }: ReferralsProps) {
    const getStatusColor = (status: string) => {
        switch (status) {
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'available': return 'bg-green-100 text-green-800';
            case 'withdrawn': return 'bg-gray-100 text-gray-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <DashboardLayout user={auth.user} header="Referrals">
            <div className="space-y-6">
                {/* Summary Stats */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Referrals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{referrals.length}</div>
                            <p className="text-xs text-gray-500">Users you've referred</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Active Agents</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {referrals.filter(r => r.referred_user.role === 'agent').length}
                            </div>
                            <p className="text-xs text-gray-500">Referrals who became agents</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-gray-600">Total Commissions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                ₵{(referralCommissions?.reduce((sum, c) => sum + (Number(c.referral_amount) || 0), 0) || 0).toFixed(2)}
                            </div>
                            <p className="text-xs text-gray-500">From referral upgrades</p>
                        </CardContent>
                    </Card>
                </div>
                {/* Referral Link */}
                <Card>
                    <CardHeader>
                        <CardTitle>Your Referral Link</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <p className="text-sm text-gray-600">Share this link to refer new agents and earn commissions:</p>
                            
                            <div>
                                <label className="block text-sm font-medium mb-2">Referral Code</label>
                                <div className="flex items-center gap-2">
                                    <input 
                                        type="text" 
                                        value={referralCode || 'Loading...'}
                                        readOnly
                                        className="flex-1 p-2 border rounded font-mono"
                                    />
                                    <button 
                                        onClick={() => navigator.clipboard.writeText(referralCode)}
                                        className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                                        disabled={!referralCode}
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label className="block text-sm font-medium mb-2">Referral URL</label>
                                <div className="flex items-center gap-2">
                                    <input 
                                        type="text" 
                                        value={referralUrl || 'Loading...'}
                                        readOnly
                                        className="flex-1 p-2 border rounded text-sm"
                                    />
                                    <button 
                                        onClick={() => navigator.clipboard.writeText(referralUrl)}
                                        className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                                        disabled={!referralUrl}
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>
                            
                            <p className="text-xs text-gray-500">
                                When someone registers using your referral link and upgrades to agent, you'll earn a 20% commission (GHS 6).
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Referred Agents */}
                <Card>
                    <CardHeader>
                        <CardTitle>Referred Agents ({referrals.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2">Name</th>
                                        <th className="text-left p-2">Email</th>
                                        <th className="text-left p-2">Role</th>
                                        <th className="text-left p-2">Joined Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {referrals.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="p-4 text-center text-gray-500">
                                                No referrals yet. Share your referral link to start earning!
                                            </td>
                                        </tr>
                                    ) : (
                                        referrals.map((referral) => (
                                            <tr key={referral.id} className="border-b">
                                                <td className="p-2">{referral.referred_user.name}</td>
                                                <td className="p-2">{referral.referred_user.email}</td>
                                                <td className="p-2">
                                                    <Badge className={referral.referred_user.role === 'agent' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'}>
                                                        {referral.referred_user.role}
                                                    </Badge>
                                                </td>
                                                <td className="p-2">{new Date(referral.referred_at).toLocaleDateString()}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {/* Referral Commissions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Referral Commissions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2">Agent</th>
                                        <th className="text-left p-2">Commission</th>
                                        <th className="text-left p-2">Percentage</th>
                                        <th className="text-left p-2">Status</th>
                                        <th className="text-left p-2">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {referralCommissions.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="p-4 text-center text-gray-500">
                                                No referral commissions yet. When your referrals upgrade to agent, you'll see commissions here.
                                            </td>
                                        </tr>
                                    ) : (
                                        referralCommissions.map((commission) => (
                                            <tr key={commission.id} className="border-b">
                                                <td className="p-2">{commission.referred_agent.name}</td>
                                                <td className="p-2 font-semibold text-green-600">₵{(Number(commission.referral_amount) || 0).toFixed(2)}</td>
                                                <td className="p-2">{commission.referral_percentage}%</td>
                                                <td className="p-2">
                                                    <Badge className={getStatusColor(commission.status)}>
                                                        {commission.status}
                                                    </Badge>
                                                </td>
                                                <td className="p-2">{new Date(commission.created_at).toLocaleDateString()}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </DashboardLayout>
    );
}