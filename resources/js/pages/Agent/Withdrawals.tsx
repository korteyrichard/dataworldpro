import React, { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { router } from '@inertiajs/react';
import { useToast } from '@/components/ui/use-toast';

interface Withdrawal {
    id: number;
    amount: number;
    phone_number?: string;
    network?: string;
    mobile_money_name?: string;
    withdrawal_fee: number;
    net_amount: number;
    status: string;
    notes: string;
    created_at: string;
}

interface WithdrawalsProps {
    auth: { user: any };
    withdrawals: Withdrawal[];
    availableBalance: number;
    minWithdrawal: number;
}

export default function Withdrawals({ auth, withdrawals, availableBalance, minWithdrawal }: WithdrawalsProps) {
    const { toast } = useToast();
    const { flash, errors } = usePage().props as any;
    const [amount, setAmount] = useState('');
    const [phoneNumber, setPhoneNumber] = useState('');
    const [network, setNetwork] = useState('');
    const [mobileMoneyName, setMobileMoneyName] = useState('');
    const [loading, setLoading] = useState(false);
    const [withdrawalFee, setWithdrawalFee] = useState(0);
    const [netAmount, setNetAmount] = useState(0);

    // Handle flash messages
    useEffect(() => {
        if (flash?.success) {
            toast({
                title: "Success",
                description: flash.success,
            });
        }
        if (flash?.error) {
            toast({
                title: "Error",
                description: flash.error,
                variant: "destructive",
            });
        }
    }, [flash, toast]);

    // Calculate fee and net amount when amount changes
    React.useEffect(() => {
        const withdrawAmount = parseFloat(amount) || 0;
        const fee = withdrawAmount * 0.02;
        const net = withdrawAmount - fee;
        setWithdrawalFee(fee);
        setNetAmount(net);
    }, [amount]);

    const handleWithdrawal = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        
        router.post(route('agent.withdrawals.request'), {
            amount: parseFloat(amount),
            phone_number: phoneNumber,
            network: network,
            mobile_money_name: mobileMoneyName
        }, {
            onFinish: () => {
                setLoading(false);
            },
            onSuccess: () => {
                setAmount('');
                setPhoneNumber('');
                setNetwork('');
                setMobileMoneyName('');
            }
        });
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
            case 'approved': return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
            case 'rejected': return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400';
            default: return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
        }
    };

    return (
        <DashboardLayout user={auth.user} header="Withdrawals">
            <div className="space-y-6">
                {/* Request Withdrawal */}
                <Card>
                    <CardHeader>
                        <CardTitle>Request Withdrawal</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <p>Available Balance: <strong>₵{availableBalance.toFixed(2)}</strong></p>
                            <p className="text-sm text-muted-foreground">Minimum withdrawal: ₵{minWithdrawal}</p>
                            <p className="text-sm text-destructive">Note: A 2% withdrawal fee will be deducted from your withdrawal amount.</p>
                            
                            <form onSubmit={handleWithdrawal} className="space-y-4">
                                <div>
                                    <Label htmlFor="amount">Withdrawal Amount</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        step="0.01"
                                        min={minWithdrawal}
                                        max={availableBalance}
                                        placeholder={`Enter amount (min ₵${minWithdrawal})`}
                                        value={amount}
                                        onChange={(e) => setAmount(e.target.value)}
                                        className={errors?.amount ? 'border-red-500' : ''}
                                        required
                                    />
                                    {errors?.amount && (
                                        <p className="text-sm text-red-500 mt-1">{errors.amount}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="phone_number">Phone Number</Label>
                                    <Input
                                        id="phone_number"
                                        type="tel"
                                        placeholder="0241234567"
                                        value={phoneNumber}
                                        onChange={(e) => {
                                            const cleaned = e.target.value.replace(/\D/g, '').slice(0, 10);
                                            setPhoneNumber(cleaned);
                                        }}
                                        className={errors?.phone_number ? 'border-red-500' : ''}
                                        required
                                    />
                                    {errors?.phone_number && (
                                        <p className="text-sm text-red-500 mt-1">{errors.phone_number}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="network">Mobile Money Network</Label>
                                    <Select value={network} onValueChange={setNetwork} required>
                                        <SelectTrigger className={errors?.network ? 'border-red-500' : ''}>
                                            <SelectValue placeholder="Select network" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="MTN">MTN Mobile Money</SelectItem>
                                            <SelectItem value="TELECEL">Telecel Cash</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors?.network && (
                                        <p className="text-sm text-red-500 mt-1">{errors.network}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="mobile_money_name">Mobile Money Account Name</Label>
                                    <Input
                                        id="mobile_money_name"
                                        type="text"
                                        placeholder="Enter the name on your mobile money account"
                                        value={mobileMoneyName}
                                        onChange={(e) => setMobileMoneyName(e.target.value)}
                                        className={errors?.mobile_money_name ? 'border-red-500' : ''}
                                        required
                                    />
                                    {errors?.mobile_money_name && (
                                        <p className="text-sm text-red-500 mt-1">{errors.mobile_money_name}</p>
                                    )}
                                </div>
                                
                                {amount && parseFloat(amount) >= minWithdrawal && (
                                    <div className="bg-muted/50 p-3 rounded-lg space-y-1 border">
                                        <div className="flex justify-between text-sm">
                                            <span>Withdrawal Amount:</span>
                                            <span>₵{parseFloat(amount).toFixed(2)}</span>
                                        </div>
                                        <div className="flex justify-between text-sm text-destructive">
                                            <span>Withdrawal Fee (2%):</span>
                                            <span>-₵{withdrawalFee.toFixed(2)}</span>
                                        </div>
                                        <div className="flex justify-between font-medium border-t pt-1">
                                            <span>You will receive:</span>
                                            <span>₵{netAmount.toFixed(2)}</span>
                                        </div>
                                        {phoneNumber && network && (
                                            <div className="flex justify-between text-sm text-primary border-t pt-1">
                                                <span>To:</span>
                                                <span>{network} ({phoneNumber})</span>
                                            </div>
                                        )}
                                    </div>
                                )}
                                
                                <Button 
                                    type="submit" 
                                    disabled={loading || !amount || !phoneNumber || !network || !mobileMoneyName} 
                                    className="w-full"
                                >
                                    {loading ? 'Processing...' : 'Request Withdrawal'}
                                </Button>
                            </form>
                        </div>
                    </CardContent>
                </Card>

                {/* Withdrawal History */}
                <Card>
                    <CardHeader>
                        <CardTitle>Withdrawal History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2">Amount</th>
                                        <th className="text-left p-2">Mobile Money</th>
                                        <th className="text-left p-2">Fee</th>
                                        <th className="text-left p-2">Net Amount</th>
                                        <th className="text-left p-2">Status</th>
                                        <th className="text-left p-2">Notes</th>
                                        <th className="text-left p-2">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {withdrawals.map((withdrawal) => (
                                        <tr key={withdrawal.id} className="border-b">
                                            <td className="p-2">₵{Number(withdrawal.amount || 0).toFixed(2)}</td>
                                            <td className="p-2">
                                                {withdrawal.network && withdrawal.phone_number ? (
                                                    <div className="text-sm">
                                                        <div>{withdrawal.network}</div>
                                                        <div className="text-muted-foreground">{withdrawal.phone_number}</div>
                                                        {withdrawal.mobile_money_name && (
                                                            <div className="text-muted-foreground text-xs">{withdrawal.mobile_money_name}</div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </td>
                                            <td className="p-2 text-destructive">₵{Number(withdrawal.withdrawal_fee || 0).toFixed(2)}</td>
                                            <td className="p-2 font-medium">₵{Number(withdrawal.net_amount || withdrawal.amount || 0).toFixed(2)}</td>
                                            <td className="p-2">
                                                <Badge className={getStatusColor(withdrawal.status)}>
                                                    {withdrawal.status}
                                                </Badge>
                                            </td>
                                            <td className="p-2">{withdrawal.notes || '-'}</td>
                                            <td className="p-2">{new Date(withdrawal.created_at).toLocaleDateString()}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </DashboardLayout>
    );
}