import { APIRequestContext, request } from "@playwright/test";

const {
    QLIRO_API_KEY,
    QLIRO_API_SECRET,
} = process.env;

export const GetQliroApiClient = async (): Promise<APIRequestContext> => {
    return await request.newContext({
        baseURL: `https://pago.qit.nu/`,
        extraHTTPHeaders: {
            Authorization: `Basic ${Buffer.from(
                `${QLIRO_API_KEY ?? 'key'}:${QLIRO_API_SECRET ?? 'secret'}`
            ).toString('base64')}`,
        },
    });
}

export const SetQliroSettings = async (wcApiClient: APIRequestContext) => {
    // Set api credentials and enable the gateway.
    if (QLIRO_API_KEY) {
        const settings = {
            enabled: true,
            settings: {
                testmode: "yes",
                logging: "yes",
                test_api_key: QLIRO_API_KEY,
                test_api_secret: QLIRO_API_SECRET,
            }
        };

        // Update settings.
        await wcApiClient.post('payment_gateways/qliro_one', { data: settings });
    }
}
