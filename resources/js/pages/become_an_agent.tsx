import { Head, useForm } from '@inertiajs/react';

export default function BecomeAnAgent() {
    const { data, setData, post, processing, errors } = useForm({
        amount: 40
    });

    const handleBecomeAgent = (e: React.FormEvent) => {
        e.preventDefault();
        post('/become_an_agent');
    };

    return (
        <>
            <Head title="Become an Agent - SuperData" />

            <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-600 via-purple-600 to-purple-700 px-4 py-12">
                <div className="bg-white rounded-3xl shadow-xl p-10 max-w-2xl text-center space-y-6">
                    <h1 className="text-3xl sm:text-4xl font-bold text-gray-800">
                        Become a SuperData Agent
                    </h1>
                    <p className="text-gray-600 text-lg">
                        As an agent, you’ll earn commissions by helping others buy affordable data bundles. 
                        Start your journey to daily earnings today with just a click.
                    </p>

                    <form onSubmit={handleBecomeAgent}>
                        <input type="hidden" name="amount" value="40" />
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-block mt-4 px-8 py-4 bg-green-600 text-white text-lg font-semibold rounded-full shadow-md hover:bg-green-700 hover:-translate-y-1 transition-all duration-300 disabled:opacity-50"
                        >
                            {processing ? 'Processing...' : 'Pay GHS 40 to Become an Agent'}
                        </button>
                        {errors.message && <p className="text-red-500 text-sm mt-2">{errors.message}</p>}
                    </form>
                </div>
            </div>
        </>
    );
}
