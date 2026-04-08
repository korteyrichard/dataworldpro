import { Head, useForm } from '@inertiajs/react';

interface Props {
    agentFee: number;
    referrer?: {
        id: number;
        name: string;
        email: string;
    };
    referralCode?: string;
}

export default function UpgradeToAgent({ agentFee, referrer, referralCode }: Props) {
    const { data, setData, post, processing, errors } = useForm({});

    const handleUpgrade = (e: React.FormEvent) => {
        e.preventDefault();
        post('/upgrade-to-agent');
    };

    // Ensure agentFee is a number
    const fee = Number(agentFee) || 30; // Default to 30 if invalid
    const transactionFee = fee * 0.01;
    const totalAmount = fee + transactionFee;

    return (
        <>
            <Head title="Upgrade to Agent - SuperData" />

            <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-600 via-purple-600 to-purple-700 px-4 py-12">
                <div className="bg-white rounded-3xl shadow-xl p-10 max-w-2xl text-center space-y-6">
                    <h1 className="text-3xl sm:text-4xl font-bold text-gray-800">
                        Upgrade to Agent
                    </h1>
                    <p className="text-gray-600 text-lg">
                        Upgrade to agent status to gain API access, earn commissions, and get cheaper prices for data bundles.
                    </p>

                    {referrer && (
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p className="text-blue-800 font-medium">
                                You were referred by: <span className="font-bold">{referrer.name}</span>
                            </p>
                            <p className="text-blue-600 text-sm">{referrer.email}</p>
                        </div>
                    )}

                    <form onSubmit={handleUpgrade}>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-block mt-4 px-8 py-4 bg-green-600 text-white text-lg font-semibold rounded-full shadow-md hover:bg-green-700 hover:-translate-y-1 transition-all duration-300 disabled:opacity-50"
                        >
                            {processing ? 'Processing...' : `Pay GHS ${totalAmount.toFixed(2)} to Upgrade to Agent`}
                        </button>
                        <p className="text-sm text-gray-500 mt-2">
                            Fee: GHS {fee.toFixed(2)} + Transaction fee: GHS {transactionFee.toFixed(2)}
                        </p>
                        {errors.message && <p className="text-red-500 text-sm mt-2">{errors.message}</p>}
                    </form>
                </div>
            </div>
        </>
    );
}