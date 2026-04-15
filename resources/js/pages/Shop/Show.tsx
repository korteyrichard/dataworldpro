import React, { useState, useEffect } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ShoppingCart, Store, User, X, Search, MessageCircle, Youtube } from 'lucide-react';

interface Agent {
    name?: string;
    phone?: string;
}

interface Shop {
    id?: number;
    name?: string;
    slug?: string;
    primary_color?: string;
    background_color?: string;
}

interface Product {
    id: number;
    product?: { name: string; network: string };
    variant?: { name: string; price: number; variant_attributes: any };
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
    const [selectedNetwork, setSelectedNetwork] = useState('MTN');
    const [email, setEmail] = useState('');
    const [beneficiaryNumber, setBeneficiaryNumber] = useState('');
    const [bundleSize, setBundleSize] = useState('');
    const [loading, setLoading] = useState(false);
    const [showTrackModal, setShowTrackModal] = useState(false);
    const [availableSizes, setAvailableSizes] = useState<Array<{value: string, label: string, price: number}>>([]);
    const [loadingSizes, setLoadingSizes] = useState(false);
    
    const { data, setData, post, processing, errors } = useForm({
        payment_reference: '',
        beneficiary_number: ''
    });

    const networks = [
        { id: 'MTN', name: 'MTN', icon: '/mtnlogo.jpeg', color: 'bg-yellow-500' },
        { id: 'TELECEL', name: 'Telecel', icon: '/telecellogo.png', color: 'bg-red-500' },
        { id: 'ISHARE', name: 'Ishare', icon: '/atlogo.png', color: 'bg-blue-500' },
        { id: 'BIGTIME', name: 'Bigtime', icon: '/atlogo.png', color: 'bg-purple-500' }
    ];

    const getNetworkButtonColors = (networkId: string) => {
        switch (networkId) {
            case 'MTN': return 'bg-yellow-500 hover:bg-yellow-600';
            case 'TELECEL': return 'bg-red-500 hover:bg-red-600';
            case 'ISHARE': return 'bg-blue-500 hover:bg-blue-600';
            case 'BIGTIME': return 'bg-purple-500 hover:bg-purple-600';
            default: return 'bg-gray-500 hover:bg-gray-600';
        }
    };

    const fetchBundleSizes = async (network: string) => {
        setLoadingSizes(true);
        try {
            // Filter products by network and extract available sizes
            const networkProducts = products?.filter(product => {
                const productNetwork = product.product?.network?.toUpperCase();
                return productNetwork === network.toUpperCase();
            }) || [];

            const sizes = networkProducts.map(product => {
                const variant = product.variant;
                let sizeLabel = 'Unknown Size';
                let sizeValue = '';
                
                if (variant?.variant_attributes && typeof variant.variant_attributes === 'object') {
                    const attributes = variant.variant_attributes;
                    const sizeKeys = ['size', 'Size', 'SIZE', 'bundle', 'Bundle', 'BUNDLE'];
                    
                    for (const key of sizeKeys) {
                        if (attributes[key]) {
                            sizeLabel = attributes[key];
                            sizeValue = attributes[key];
                            break;
                        }
                    }
                    
                    // If no specific size key found, use first attribute
                    if (!sizeValue) {
                        const values = Object.values(attributes);
                        if (values.length > 0) {
                            sizeLabel = values[0] as string;
                            sizeValue = values[0] as string;
                        }
                    }
                }
                
                return {
                    value: sizeValue,
                    label: sizeLabel,
                    price: parseFloat(product.agent_price?.toString() || '0')
                };
            }).filter(size => size.value); // Remove items without valid size values

            // Remove duplicates based on value
            const uniqueSizes = sizes.filter((size, index, self) => 
                index === self.findIndex(s => s.value === size.value)
            );

            setAvailableSizes(uniqueSizes);
        } catch (error) {
            setAvailableSizes([]);
        } finally {
            setLoadingSizes(false);
        }
    };

    const handleNetworkChange = (networkId: string) => {
        setSelectedNetwork(networkId);
        setBundleSize('');
        fetchBundleSizes(networkId);
    };

    useEffect(() => {
        fetchBundleSizes(selectedNetwork);
    }, [products]);

    const handlePayment = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!email || !beneficiaryNumber || !bundleSize) return;
        
        setLoading(true);
        
        try {
            const selectedSize = availableSizes.find(size => size.value === bundleSize);
            if (!selectedSize) {
                alert('Please select a valid bundle size');
                setLoading(false);
                return;
            }

            // Find the agent product that matches the selected network and bundle size
            const matchingProduct = products?.find(product => {
                const productNetwork = product.product?.network?.toUpperCase();
                const selectedNetworkUpper = selectedNetwork.toUpperCase();
                
                // Check if networks match
                if (productNetwork !== selectedNetworkUpper) return false;
                
                // Check if the variant attributes match the selected bundle size
                const variant = product.variant;
                if (!variant?.variant_attributes) return false;
                
                const attributes = variant.variant_attributes;
                if (typeof attributes === 'object') {
                    const sizeKeys = ['size', 'Size', 'SIZE', 'bundle', 'Bundle', 'BUNDLE'];
                    for (const key of sizeKeys) {
                        if (attributes[key] === bundleSize) return true;
                    }
                    // Also check if any attribute value matches
                    const values = Object.values(attributes);
                    return values.includes(bundleSize);
                }
                return false;
            });

            if (!matchingProduct) {
                alert(`No product found for ${selectedNetwork} ${bundleSize}. Please contact the agent.`);
                setLoading(false);
                return;
            }

            const response = await fetch('/guest/payment/initialize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    email,
                    agent_product_id: matchingProduct.id,
                    beneficiary_number: beneficiaryNumber
                })
            });
            
            const result = await response.json();
            
            if (result.status) {
                window.location.href = result.data.authorization_url;
            } else {
                alert(`Payment initialization failed: ${result.message || 'Unknown error'}`);
            }
        } catch (error) {
            alert(`An error occurred: ${error.message || 'Unknown error'}`);
        } finally {
            setLoading(false);
        }
    };

    const handleWhatsAppContact = () => {
        if (agent?.phone) {
            const whatsappUrl = `https://wa.me/${agent.phone.replace(/[^0-9]/g, '')}`;
            window.open(whatsappUrl, '_blank');
        }
    };

    const handleTrackOrder = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('shop.find-order', shop?.slug || ''));
    };
    return (
        <>
            <Head title={shop?.name || 'Shop'} />
            
            <div className="min-h-screen" style={{ backgroundColor: shop?.background_color || '#F1F5F9' }}>
                {/* Header */}
                <div className="bg-white shadow-sm border-b" style={{ backgroundColor: shop?.primary_color || '#3B82F6' }}>
                    <div className="container mx-auto px-4 py-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-4">
                                <div className="bg-white bg-opacity-20 p-3 rounded-full flex items-center justify-center w-12 h-12">
                                    <span className="text-xl font-bold" style={{ color: shop?.primary_color || '#3B82F6' }}>
                                        {shop?.name ? shop.name.charAt(0).toUpperCase() : 'S'}
                                    </span>
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold text-white">{shop?.name || 'Shop'}</h1>
                                    <p className="text-white text-opacity-80">by {agent?.name || 'Agent'}</p>
                                </div>
                            </div>
                            <Badge variant="secondary" className="bg-white bg-opacity-20 text-white border-white border-opacity-30">
                                {networks.length} Networks Available
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
                            <DialogContent className="sm:max-w-md bg-white dark:bg-gray-800">
                                <DialogHeader>
                                    <DialogTitle className="text-center text-gray-900 dark:text-gray-100">Track Your Order</DialogTitle>
                                </DialogHeader>
                                <div className="text-center mb-4">
                                    <div className="inline-flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full mb-3 mx-auto">
                                        <Search className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <p className="text-gray-600 dark:text-gray-300 text-sm">
                                        Enter your order details to check status
                                    </p>
                                </div>
                                <form onSubmit={handleTrackOrder} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium mb-1 text-gray-900 dark:text-gray-100">Paystack Reference</label>
                                        <Input
                                            type="text"
                                            placeholder="Enter paystack reference"
                                            value={data.payment_reference}
                                            onChange={(e) => setData('payment_reference', e.target.value)}
                                            required
                                            className="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
                                        />
                                        <p className="text-red-500 text-xs mt-1">This can be found in your email/gmail message</p>
                                        {errors.payment_reference && (
                                            <p className="text-red-500 text-xs mt-1">{errors.payment_reference}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium mb-1 text-gray-900 dark:text-gray-100">Beneficiary Phone Number (10 digits)</label>
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
                                            className="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
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
                                            style={{ backgroundColor: shop?.primary_color || '#3B82F6' }}
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
                        
                        {agent?.phone && (
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

                {/* Order Section */}
                <div className="container mx-auto px-4 py-8">
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden max-w-4xl mx-auto">
                        <div className="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Place Your Order</h3>
                            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">Select network, enter details, and choose your bundle size</p>
                        </div>

                        <div className="p-6">
                            {/* Network Selection */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium mb-3 text-gray-900 dark:text-gray-100">Select Network</label>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    {networks.map((network) => (
                                        <button
                                            key={network.id}
                                            onClick={() => handleNetworkChange(network.id)}
                                            className={`p-4 rounded-lg border-2 transition-all duration-200 ${
                                                selectedNetwork === network.id
                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                                    : 'border-gray-200 dark:border-gray-600 hover:border-gray-300'
                                            }`}
                                        >
                                            <div className="text-center">
                                                <div className={`w-12 h-12 ${network.color} rounded-lg flex items-center justify-center mx-auto mb-2`}>
                                                    <img src={network.icon} alt={`${network.name} logo`} className="w-8 h-8" />
                                                </div>
                                                <div className="font-medium text-sm text-gray-900 dark:text-gray-100">{network.name}</div>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Order Form */}
                            <form onSubmit={handlePayment} className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label className="block text-sm font-medium mb-2 text-gray-900 dark:text-gray-100">Email Address</label>
                                        <Input
                                            type="email"
                                            placeholder="Enter your email"
                                            value={email}
                                            onChange={(e) => setEmail(e.target.value)}
                                            required
                                            className="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
                                        />
                                    </div>
                                    
                                    <div>
                                        <label className="block text-sm font-medium mb-2 text-gray-900 dark:text-gray-100">Beneficiary Number (10 digits)</label>
                                        <Input
                                            type="text"
                                            placeholder="0240000000"
                                            value={beneficiaryNumber}
                                            onChange={(e) => {
                                                const value = e.target.value.replace(/\D/g, '').slice(0, 10);
                                                setBeneficiaryNumber(value);
                                            }}
                                            maxLength={10}
                                            pattern="[0-9]{10}"
                                            required
                                            className="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600"
                                        />
                                        {beneficiaryNumber && beneficiaryNumber.length !== 10 && (
                                            <p className="text-red-500 text-xs mt-1">Phone number must be exactly 10 digits</p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-2 text-gray-900 dark:text-gray-100">Bundle Size</label>
                                    <Select value={bundleSize} onValueChange={setBundleSize}>
                                        <SelectTrigger className="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600">
                                            <SelectValue placeholder={loadingSizes ? "Loading sizes..." : "Select bundle size"} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableSizes.map((size) => (
                                                <SelectItem key={size.value} value={size.value}>
                                                    {size.label} - ₵{size.price}
                                                </SelectItem>
                                            ))}
                                            {availableSizes.length === 0 && !loadingSizes && (
                                                <SelectItem value="no-sizes" disabled>
                                                    No sizes available for {selectedNetwork}
                                                </SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {bundleSize && (
                                    <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-gray-600 dark:text-gray-400">Total Amount:</span>
                                            <span className="text-lg font-bold text-green-600 dark:text-green-400">
                                                ₵{availableSizes.find(size => size.value === bundleSize)?.price || 0}
                                            </span>
                                        </div>
                                    </div>
                                )}

                                <Button 
                                    type="submit" 
                                    disabled={loading || !email || beneficiaryNumber.length !== 10 || !bundleSize}
                                    className={`w-full text-white py-3 ${getNetworkButtonColors(selectedNetwork)}`}
                                >
                                    <ShoppingCart className="h-4 w-4 mr-2" />
                                    {loading ? 'Processing...' : 'Buy Now'}
                                </Button>
                            </form>
                        </div>
                    </div>

                    {/* Terms of Service */}
                    <div className="bg-red-50 border border-red-200 rounded-lg p-4 mt-6 max-w-4xl mx-auto">
                        <h3 className="text-lg font-semibold text-red-800 mb-2">⚠️ NOT SUPPORTED</h3>
                        <p className="text-red-700 text-sm mb-2">Our data offers do not support the following:</p>
                        <ul className="text-red-700 text-sm space-y-1">
                            <li>• SIM with airtime debt</li>
                            <li>• Router SIM</li>
                            <li>• Broadband SIM</li>
                            <li>• EVD SIM</li>
                            <li>• Transfer SIM</li>
                            <li>• Merchant SIM</li>
                            <li>• Wrong numbers</li>
                        </ul>
                    </div>
                </div>

            </div>
        </>
    );
}