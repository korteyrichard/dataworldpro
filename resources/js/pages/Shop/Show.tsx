import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ShoppingCart, Store, User, X, Search, MessageCircle, Youtube } from 'lucide-react';

interface Agent {
    name: string;
    phone: string;
}

interface Shop {
    id: number;
    name: string;
    slug: string;
    primary_color?: string;
    background_color?: string;
}

interface Product {
    id: number;
    product: { name: string; network: string };
    variant: { name: string; price: number; variant_attributes: any };
    agent_price: number;
    commission_amount: number;
}

interface ShopShowProps {
    shop: Shop;
    products: Product[];
    agent: Agent;
    trackOrderVideoUrl: string;
}

export default function Show({ shop, products, agent, trackOrderVideoUrl }: ShopShowProps) {
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
    const [email, setEmail] = useState('');
    const [beneficiaryNumber, setBeneficiaryNumber] = useState('');
    const [loading, setLoading] = useState(false);
    const [showTrackModal, setShowTrackModal] = useState(false);
    
    const { data, setData, post, processing, errors } = useForm({
        payment_reference: '',
        beneficiary_number: ''
    });

    const getNetworkColor = (network: string) => {
        const networkLower = network?.toLowerCase() || '';
        if (networkLower.includes('mtn')) return {
            bg: 'bg-yellow-400',
            border: 'border-yellow-500',
            text: 'text-yellow-900',
            badge: 'bg-yellow-600 text-white'
        };
        if (networkLower.includes('telecel')) return {
            bg: 'bg-red-400',
            border: 'border-red-500', 
            text: 'text-red-900',
            badge: 'bg-red-600 text-white'
        };
        if (networkLower.includes('ishare') || networkLower.includes('bigtime')) return {
            bg: 'bg-blue-400',
            border: 'border-blue-500',
            text: 'text-blue-900', 
            badge: 'bg-blue-600 text-white'
        };
        return {
            bg: 'bg-gray-400',
            border: 'border-gray-500',
            text: 'text-gray-900',
            badge: 'bg-gray-600 text-white'
        };
    };

    const getSize = (variant: any) => {
        if (!variant?.variant_attributes) return null;
        const attributes = variant.variant_attributes;
        // Look for size-related attributes
        if (typeof attributes === 'object') {
            const sizeKeys = ['size', 'Size', 'SIZE', 'bundle', 'Bundle', 'BUNDLE'];
            for (const key of sizeKeys) {
                if (attributes[key]) return attributes[key];
            }
            // If no specific size key, return the first attribute value
            const values = Object.values(attributes);
            return values.length > 0 ? values[0] : null;
        }
        return null;
    };

    const handleBuyNow = (product: Product) => {
        setSelectedProduct(product);
        setShowPaymentModal(true);
    };

    const handlePayment = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedProduct) return;
        
        setLoading(true);
        
        try {
            const response = await fetch('/guest/payment/initialize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    email,
                    agent_product_id: selectedProduct.id,
                    beneficiary_number: beneficiaryNumber
                })
            });
            
            const result = await response.json();
            
            if (result.status) {
                window.location.href = result.data.authorization_url;
            } else {
                alert('Payment initialization failed');
            }
        } catch (error) {
            alert('An error occurred');
        } finally {
            setLoading(false);
        }
    };

    const handleWhatsAppContact = () => {
        if (agent.phone) {
            const whatsappUrl = `https://wa.me/${agent.phone.replace(/[^0-9]/g, '')}`;
            window.open(whatsappUrl, '_blank');
        }
    };

    const handleTrackOrder = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('shop.find-order', shop.slug));
    };
    return (
        <>
            <Head title={`${shop.name} - Agent Shop`} />
            
            <div className="min-h-screen" style={{ backgroundColor: shop.background_color || '#F1F5F9' }}>
                {/* Header */}
                <div className="bg-white shadow-sm border-b" style={{ backgroundColor: shop.primary_color || '#3B82F6' }}>
                    <div className="container mx-auto px-4 py-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-4">
                                <div className="bg-white bg-opacity-20 p-3 rounded-full">
                                    <Store className="h-6 w-6 text-white" />
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold text-white">{shop.name}</h1>
                                    <p className="text-white text-opacity-80">by {agent.name}</p>
                                </div>
                            </div>
                            <Badge variant="secondary" className="bg-white bg-opacity-20 text-white border-white border-opacity-30">
                                {products.length} Products
                            </Badge>
                        </div>
                    </div>
                </div>

                {/* Track Order Button */}
                <div className="container mx-auto px-4 py-4">
                    <div className="flex justify-center gap-3">
                        <Dialog open={showTrackModal} onOpenChange={setShowTrackModal}>
                            <DialogTrigger asChild>
                                <Button 
                                    variant="outline" 
                                    className="bg-white hover:bg-gray-50 text-gray-700 border-gray-300"
                                >
                                    <Search className="h-4 w-4 mr-2" />
                                    Track Order
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-md">
                                <DialogHeader>
                                    <DialogTitle className="text-center">Track Your Order</DialogTitle>
                                </DialogHeader>
                                <div className="text-center mb-4">
                                    <div className="inline-flex items-center justify-center w-12 h-12 bg-blue-100 rounded-full mb-3 mx-auto">
                                        <Search className="h-6 w-6 text-blue-600" />
                                    </div>
                                    <p className="text-gray-600 text-sm">
                                        Enter your order details to check status
                                    </p>
                                </div>
                                <form onSubmit={handleTrackOrder} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium mb-1">Payment Reference</label>
                                        <Input
                                            type="text"
                                            placeholder="Enter payment reference"
                                            value={data.payment_reference}
                                            onChange={(e) => setData('payment_reference', e.target.value)}
                                            required
                                        />
                                        {errors.payment_reference && (
                                            <p className="text-red-500 text-xs mt-1">{errors.payment_reference}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium mb-1">Beneficiary Phone Number (10 digits)</label>
                                        <Input
                                            type="text"
                                            placeholder="Enter 10-digit phone number"
                                            value={data.beneficiary_number}
                                            onChange={(e) => {
                                                const value = e.target.value.replace(/\D/g, '').slice(0, 10);
                                                setData('beneficiary_number', value);
                                            }}
                                            maxLength={10}
                                            pattern="[0-9]{10}"
                                            required
                                        />
                                        {data.beneficiary_number && data.beneficiary_number.length !== 10 && (
                                            <p className="text-red-500 text-xs mt-1">Phone number must be exactly 10 digits</p>
                                        )}
                                        {errors.beneficiary_number && (
                                            <p className="text-red-500 text-xs mt-1">{errors.beneficiary_number}</p>
                                        )}
                                    </div>
                                    <div className="flex gap-3 pt-4">
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            onClick={() => setShowTrackModal(false)}
                                            className="flex-1"
                                        >
                                            Cancel
                                        </Button>
                                        <Button 
                                            type="submit" 
                                            disabled={processing || !data.payment_reference || data.beneficiary_number.length !== 10}
                                            className="flex-1 text-white"
                                            style={{ backgroundColor: shop.primary_color || '#3B82F6' }}
                                        >
                                            <Search className="h-4 w-4 mr-2" />
                                            {processing ? 'Searching...' : 'Track Order'}
                                        </Button>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                        
                        {trackOrderVideoUrl && (
                            <Button 
                                onClick={() => window.open(trackOrderVideoUrl, '_blank')}
                                variant="outline" 
                                className="bg-red-500 hover:bg-red-600 text-white border-red-500"
                            >
                                <Youtube className="h-4 w-4 mr-2" />
                                How to Track Order
                            </Button>
                        )}
                        
                        {agent.phone && (
                            <Button 
                                onClick={handleWhatsAppContact}
                                className="bg-green-500 hover:bg-green-600 text-white"
                            >
                                <MessageCircle className="h-4 w-4 mr-2" />
                                Contact Agent
                            </Button>
                        )}
                    </div>
                </div>

                {/* Products Section */}
                <div className="container mx-auto px-4 py-8">
                    {products.length > 0 ? (
                        <>
                            <h2 className="text-2xl font-semibold text-gray-900 mb-6">Available Products</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                {products.map((product) => {
                                    const size = getSize(product.variant);
                                    const networkColors = getNetworkColor(product.product.network);
                                    
                                    return (
                                        <Card key={product.id} className={`hover:shadow-lg transition-shadow duration-200 shadow-md overflow-hidden ${networkColors.bg} ${networkColors.border} border-2`}>
                                            <CardHeader className="pb-3">
                                                <CardTitle className="text-lg font-semibold text-white line-clamp-2">
                                                    {product.product.name}
                                                </CardTitle>
                                                <div className="flex gap-2 flex-wrap">
                                                    <Badge className={`w-fit ${networkColors.badge} border-0`}>
                                                        {product.product.network}
                                                    </Badge>
                                                    {size && (
                                                        <Badge variant="outline" className="w-fit bg-white text-gray-800 border-white">
                                                            {size}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardHeader>
                                            <CardContent className="pt-0">
                                                <div className="space-y-4">
                                                    <div className="flex items-baseline justify-between">
                                                        <div>
                                                            <p className="text-2xl font-bold text-white">
                                                                ₵{parseFloat(product.agent_price || 0).toFixed(2)}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <Button 
                                                        className="w-full text-white bg-white bg-opacity-20 hover:bg-opacity-30 border border-white border-opacity-50" 
                                                        onClick={() => handleBuyNow(product)}
                                                    >
                                                        <ShoppingCart className="h-4 w-4 mr-2" />
                                                        Buy Now
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                        </>
                    ) : (
                        <div className="text-center py-16">
                            <div className="bg-white rounded-lg shadow-md p-8 max-w-md mx-auto">
                                <Store className="h-16 w-16 text-gray-400 mx-auto mb-4" />
                                <h3 className="text-xl font-semibold text-gray-900 mb-2">No Products Yet</h3>
                                <p className="text-gray-600">This shop is still being set up. Check back soon for amazing products!</p>
                            </div>
                        </div>
                    )}
                </div>

                {/* Payment Modal */}
                {showPaymentModal && selectedProduct && (
                    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                        <div className="bg-white rounded-lg max-w-md w-full p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-semibold">Complete Purchase</h3>
                                <button 
                                    onClick={() => setShowPaymentModal(false)}
                                    className="text-gray-500 hover:text-gray-700"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                            
                            <div className="mb-4 p-3 bg-gray-50 rounded">
                                <p className="font-medium">{selectedProduct.product.name}</p>
                                <div className="flex gap-2 mt-1">
                                    <Badge className={`text-xs ${getNetworkColor(selectedProduct.product.network).badge} border-0`}>
                                        {selectedProduct.product.network}
                                    </Badge>
                                    {getSize(selectedProduct.variant) && (
                                        <Badge variant="outline" className="text-xs bg-white">
                                            {getSize(selectedProduct.variant)}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-lg font-bold text-green-600 mt-2">
                                    ₵{parseFloat(selectedProduct.agent_price || 0).toFixed(2)}
                                </p>
                            </div>
                            
                            <form onSubmit={handlePayment} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Email Address</label>
                                    <Input
                                        type="email"
                                        placeholder="Enter your email"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        required
                                    />
                                </div>
                                
                                <div>
                                    <label className="block text-sm font-medium mb-1">Beneficiary Number (10 digits)</label>
                                    <Input
                                        type="text"
                                        placeholder="Enter 10-digit phone number"
                                        value={beneficiaryNumber}
                                        onChange={(e) => {
                                            const value = e.target.value.replace(/\D/g, '').slice(0, 10);
                                            setBeneficiaryNumber(value);
                                        }}
                                        maxLength={10}
                                        pattern="[0-9]{10}"
                                        required
                                    />
                                    {beneficiaryNumber && beneficiaryNumber.length !== 10 && (
                                        <p className="text-red-500 text-xs mt-1">Phone number must be exactly 10 digits</p>
                                    )}
                                </div>
                                
                                <div className="flex gap-3 pt-4">
                                    <Button 
                                        type="button" 
                                        variant="outline" 
                                        onClick={() => setShowPaymentModal(false)}
                                        className="flex-1"
                                    >
                                        Cancel
                                    </Button>
                                    <Button 
                                        type="submit" 
                                        disabled={loading || beneficiaryNumber.length !== 10}
                                        className="flex-1 text-white"
                                        style={{ backgroundColor: shop.primary_color || '#3B82F6' }}
                                    >
                                        {loading ? 'Processing...' : 'Pay Now'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}

                {/* Footer */}
                <div className="bg-white border-t mt-16">
                    <div className="container mx-auto px-4 py-6">
                        <div className="text-center text-gray-600">
                            <p>Powered by <span className="font-semibold text-blue-600">ProDataWorld</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}