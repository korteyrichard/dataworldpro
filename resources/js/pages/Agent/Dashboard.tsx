import React, { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DollarSign, Users, CreditCard, ShoppingBag } from 'lucide-react';
import { useForm } from '@inertiajs/react';

interface AgentDashboardProps {
    auth: { user: any };
    stats: {
        total_sales: number;
        pending_commissions: number;
        available_balance: number;
        withdrawn_balance: number;
        referral_earnings: number;
    };
    shop: any;
    referralCode: string;
    referralUrl: string;
}

export default function AgentDashboard({ auth, stats, shop, referralCode, referralUrl }: AgentDashboardProps) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        primary_color: '#3b82f6',
        background_color: '#ffffff'
    });

    const formatCurrency = (value: any): string => {
        const num = parseFloat(value) || 0;
        return num.toFixed(2);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('agent.shop.create'), {
            onSuccess: () => {
                setIsDialogOpen(false);
                reset();
            }
        });
    };

    return (
        <DashboardLayout user={auth.user} header="Store Dashboard">
            <div className="space-y-6">
                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Sales</CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">₵{formatCurrency(stats.total_sales)}</div>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Available Balance</CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">₵{formatCurrency(stats.available_balance)}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Shop Status */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShoppingBag className="h-5 w-5" />
                            My Shop
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {shop ? (
                            <div className="space-y-2">
                                <p><strong>Shop Name:</strong> {shop.name}</p>
                                <p><strong>Shop URL:</strong> <a href={`/shop/${shop.slug}`} className="text-blue-600 hover:underline" target="_blank">/shop/{shop.slug}</a></p>
                                <p><strong>Status:</strong> <span className={shop.is_active ? 'text-green-600' : 'text-red-600'}>{shop.is_active ? 'Active' : 'Inactive'}</span></p>
                            </div>
                        ) : (
                            <div className="text-center py-4">
                                <p className="text-muted-foreground mb-4">You haven't created a shop yet.</p>
                                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button>Create My Shop</Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Create Your Shop</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSubmit} className="space-y-4">
                                            <div>
                                                <Label htmlFor="name">Shop Name</Label>
                                                <Input
                                                    id="name"
                                                    value={data.name}
                                                    onChange={(e) => setData('name', e.target.value)}
                                                    placeholder="Enter your shop name"
                                                    required
                                                />
                                                {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                                            </div>
                                            <div>
                                                <Label htmlFor="primary_color">Primary Color</Label>
                                                <Input
                                                    id="primary_color"
                                                    type="color"
                                                    value={data.primary_color}
                                                    onChange={(e) => setData('primary_color', e.target.value)}
                                                    required
                                                />
                                                {errors.primary_color && <p className="text-red-500 text-sm mt-1">{errors.primary_color}</p>}
                                            </div>
                                            <div>
                                                <Label htmlFor="background_color">Background Color</Label>
                                                <Input
                                                    id="background_color"
                                                    type="color"
                                                    value={data.background_color}
                                                    onChange={(e) => setData('background_color', e.target.value)}
                                                    required
                                                />
                                                {errors.background_color && <p className="text-red-500 text-sm mt-1">{errors.background_color}</p>}
                                            </div>
                                            <div className="flex justify-end space-x-2">
                                                <Button type="button" variant="outline" onClick={() => setIsDialogOpen(false)}>Cancel</Button>
                                                <Button type="submit" disabled={processing}>Create Shop</Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </DashboardLayout>
    );
}