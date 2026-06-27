import React, { useState } from 'react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { Head } from '@inertiajs/react';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Webhook {
    id: number;
    url: string;
    secret: string;
    is_active: boolean;
    created_at: string;
}

interface Props {
    auth: {
        user: User;
    };
}

export default function ApiDocs({ auth }: Props) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [token, setToken] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [webhooks, setWebhooks] = useState<Webhook[]>([]);
    const [webhookUrl, setWebhookUrl] = useState('');
    const [webhookLoading, setWebhookLoading] = useState(false);
    const [webhookError, setWebhookError] = useState('');

    React.useEffect(() => {
        fetchExistingToken();
    }, []);

    React.useEffect(() => {
        if (token) fetchWebhooks();
    }, [token]);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const fetchWebhooks = async () => {
        try {
            const res = await fetch('/api/v1/webhooks', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });
            if (res.ok) {
                const data = await res.json();
                setWebhooks(data.webhooks);
            }
        } catch (err) { console.error(err); }
    };

    const handleAddWebhook = async (e: React.FormEvent) => {
        e.preventDefault();
        setWebhookLoading(true);
        setWebhookError('');
        try {
            const res = await fetch('/api/v1/webhooks', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                body: JSON.stringify({ url: webhookUrl }),
            });
            if (res.ok) {
                const data = await res.json();
                setWebhooks([...webhooks, data.webhook]);
                setWebhookUrl('');
            } else {
                const data = await res.json();
                setWebhookError(data.message || 'Failed to add webhook');
            }
        } catch (err) {
            setWebhookError('Network error');
        } finally {
            setWebhookLoading(false);
        }
    };

    const handleDeleteWebhook = async (id: number) => {
        await fetch(`/api/v1/webhooks/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
        });
        setWebhooks(webhooks.filter(w => w.id !== id));
    };

    const fetchExistingToken = async () => {
        try {
            const response = await fetch('/api/v1/get-token', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.token) {
                    setToken(data.token);
                }
            }
        } catch (err) {
            console.error('Failed to fetch existing token:', err);
        }
    };

    const handleLogin = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError('');

        try {
            const response = await fetch('/api/v1/token/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (response.ok) {
                setToken(data.token);
            } else {
                setError(data.message || 'Login failed');
            }
        } catch (err) {
            setError('Network error occurred');
        } finally {
            setLoading(false);
        }
    };

    const handleLogout = async () => {
        if (token) {
            try {
                const response = await fetch('/api/v1/logout-all', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                });
                const data = await response.json();
                console.log('Logout response:', data);
            } catch (err) {
                console.error('Logout failed:', err);
            }
        }
        setToken('');
        setEmail('');
        setPassword('');
        setError('');
    };

    return (
        <DashboardLayout user={auth.user} header="API Documentation">
            <Head title="API Documentation" />

            <div className="max-w-4xl mx-auto space-y-8">
                {/* API Login Section */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">API Authentication</h2>
                    
                    {!token ? (
                        <form onSubmit={handleLogin} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Password
                                </label>
                                <input
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    required
                                />
                            </div>
                            {error && (
                                <div className="text-red-600 text-sm">{error}</div>
                            )}
                            <button
                                type="submit"
                                disabled={loading}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md disabled:opacity-50"
                            >
                                {loading ? 'Generating Token...' : 'Generate API Token'}
                            </button>
                        </form>
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    API Token
                                </label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={token}
                                        readOnly
                                        className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                    />
                                    <button
                                        onClick={() => navigator.clipboard.writeText(token)}
                                        className="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-md"
                                    >
                                        Copy
                                    </button>
                                </div>
                            </div>
                            <button
                                onClick={handleLogout}
                                className="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md"
                            >
                                Logout
                            </button>
                        </div>
                    )}
                </div>

                {/* API Documentation */}
                <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h2 className="text-xl font-semibold mb-6 text-gray-900 dark:text-gray-100">API Endpoints</h2>

                    {/* Authentication */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Authentication</h3>
                        <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                <strong>POST</strong> /api/v1/token/create
                            </p>
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Generate API token</p>
                            <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "email": "user@example.com",
  "password": "password"
}`}
                            </pre>
                        </div>
                    </div>

                    {/* Network IDs */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Network IDs</h3>
                        <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                Use these network IDs for API requests:
                            </p>
                            <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`MTN: 5
TELECEL: 6
ISHARE: 7
BIGTIME: 8`}
                            </pre>
                        </div>
                    </div>

                    {/* Orders API */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Orders API</h3>
                        <div className="space-y-4">
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/normal-orders
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get user's orders</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>POST</strong> /api/v1/normal-orders
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Create new order</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "beneficiary_number": "0241234567",
  "network_id": 1,
  "size": "1GB"
}`}
                                </pre>
                            </div>
                        </div>
                    </div>

                    {/* AFA API */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">AFA API</h3>
                        <div className="space-y-4">
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/afa
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get user's AFA orders</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/afa/products
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get available AFA products</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>POST</strong> /api/v1/afa
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">Create AFA order</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "afa_product_id": 1,
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "0241234567",
  "dob": "1990-01-01",
  "occupation": "Developer",
  "region": "Greater Accra"
}`}
                                </pre>
                            </div>
                        </div>
                    </div>

                    {/* Transactions API */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Transactions API</h3>
                        <div className="space-y-4">
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/transactions
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get all orders for authenticated user</p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto mt-2">
{`Response:
{
  "success": true,
  "data": [
    {
      "id": 123,
      "user_id": 1,
      "total": "10.00",
      "status": "completed",
      "beneficiary_number": "0241234567",
      "network": "MTN",
      "reference_id": "ORD123456",
      "transactions": [...],
      "products": [...],
      "user": {...}
    }
  ]
}`}
                                </pre>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/transactions/&#123;id&#125;
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Get single order by ID with transactions and products</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>GET</strong> /api/v1/transaction-status (Legacy)
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">Legacy endpoint - same as /api/v1/transactions</p>
                            </div>
                        </div>
                    </div>

                    {/* Authentication Headers */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Authentication Headers</h3>
                        <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                All authenticated requests must include:
                            </p>
                            <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
Accept: application/json`}
                            </pre>
                        </div>
                    </div>

                    {/* Webhooks Documentation */}
                    <div className="mb-8">
                        <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-gray-100">Webhooks (Order Status Updates)</h3>
                        <div className="space-y-4">
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    Register a webhook URL to receive real-time order status updates. When an order status changes, we send a POST request to your URL.
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                    <strong>Example URL:</strong> <code className="bg-gray-200 dark:bg-gray-600 px-1 rounded">https://yoursite.com/api/webhook/order-status</code>
                                </p>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>Payload sent to your URL:</strong>
                                </p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`{
  "event": "order.status_changed",
  "order_id": 123,
  "reference_id": "guest000123",
  "payment_reference": "PAY_xxx",
  "status": "completed",
  "beneficiary_number": "0241234567",
  "network": "MTN",
  "total": 50.00,
  "timestamp": "2026-06-21T19:30:00+00:00"
}`}
                                </pre>
                            </div>
                            <div className="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    <strong>Verifying the webhook signature:</strong>
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    Each request includes an <code className="bg-gray-200 dark:bg-gray-600 px-1 rounded">X-Webhook-Signature</code> header. Verify it like this:
                                </p>
                                <pre className="text-xs bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto">
{`// PHP example
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$expected = hash_hmac('sha256', $payload, 'YOUR_WEBHOOK_SECRET');

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Process the webhook
$data = json_decode($payload, true);
// $data['status'] => "completed", "pending", "cancelled", etc.`}
                                </pre>
                            </div>
                        </div>
                    </div>

                </div>

                {/* Webhook Management Section */}
                {token && (
                    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Manage Webhooks</h2>
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Add a URL to receive real-time POST requests whenever your order status changes.
                        </p>

                        <form onSubmit={handleAddWebhook} className="flex gap-2 mb-6">
                            <input
                                type="url"
                                value={webhookUrl}
                                onChange={(e) => setWebhookUrl(e.target.value)}
                                placeholder="https://yoursite.com/api/webhook/order-status"
                                className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                required
                            />
                            <button
                                type="submit"
                                disabled={webhookLoading}
                                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md disabled:opacity-50 whitespace-nowrap"
                            >
                                {webhookLoading ? 'Adding...' : 'Add Webhook'}
                            </button>
                        </form>
                        {webhookError && <p className="text-red-600 text-sm mb-4">{webhookError}</p>}

                        {webhooks.length > 0 ? (
                            <div className="space-y-3">
                                {webhooks.map((wh) => (
                                    <div key={wh.id} className="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{wh.url}</p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                Secret: <code className="bg-gray-200 dark:bg-gray-600 px-1 rounded select-all">{wh.secret}</code>
                                            </p>
                                        </div>
                                        <button
                                            onClick={() => handleDeleteWebhook(wh.id)}
                                            className="ml-4 text-red-600 hover:text-red-800 text-sm font-medium"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">No webhooks registered yet.</p>
                        )}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}