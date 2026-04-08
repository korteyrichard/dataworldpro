import { Head, useForm } from '@inertiajs/react';

interface Props {
    agentFee: number;
}

export default function BecomeAnAgent({ agentFee }: Props) {
    // Ensure agentFee is a number
    const fee = Number(agentFee) || 30; // Default to 30 if invalid
    
    const { data, setData, post, processing, errors } = useForm({
        amount: fee
    });

    const handleBecomeAgent = (e: React.FormEvent) => {
        e.preventDefault();
        post('/become_an_agent');
    };

    const transactionFee = fee * 0.01;
    const totalAmount = fee + transactionFee;

    return (
        <>
            <Head title="Become an Agent - SuperData" />

            <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-600 via-purple-600 to-purple-700 px-4 py-12">
                <div className="bg-white rounded-3xl shadow-xl p-10 max-w-2xl text-center space-y-6">
                    <h1 className="text-3xl sm:text-4xl font-bold text-gray-800">
                        Gain Api Access
                    </h1>
                    <p className="text-gray-600 text-lg">
                        getting access to our API means faster order processing from your website and cheaper price for data bundles.
                    </p>

                    <form onSubmit={handleBecomeAgent}>
                        <input type="hidden" name="amount" value={fee} />
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-block mt-4 px-8 py-4 bg-green-600 text-white text-lg font-semibold rounded-full shadow-md hover:bg-green-700 hover:-translate-y-1 transition-all duration-300 disabled:opacity-50"
                        >
                            {processing ? 'Processing...' : `Pay GHS ${totalAmount.toFixed(2)} to Gain API Access`}
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