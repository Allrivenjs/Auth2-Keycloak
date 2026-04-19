/**
 * Tests for the Themosis API client helper.
 * These run in Node (not jsdom) so we mock the global fetch.
 */

import { themosisApi } from "../src/lib/themosis-api";
import type { Session } from "next-auth";

const mockFetch = jest.fn();
global.fetch = mockFetch;

const session: Session = {
  accessToken: "test-access-token",
  expires: new Date(Date.now() + 3600_000).toISOString(),
  user: { email: "demo@example.com", name: "Demo User" },
};

beforeEach(() => {
  mockFetch.mockReset();
  mockFetch.mockResolvedValue({ ok: true, status: 200, json: async () => ({}) });
});

describe("themosisApi", () => {
  it("attaches Authorization header when session has accessToken", async () => {
    await themosisApi(session).get("/wp-json/auth2/v1/me");

    expect(mockFetch).toHaveBeenCalledTimes(1);
    const [, options] = mockFetch.mock.calls[0];
    expect((options.headers as Record<string, string>)["Authorization"]).toBe(
      "Bearer test-access-token"
    );
  });

  it("does NOT attach Authorization header when session is null", async () => {
    await themosisApi(null).get("/wp-json/auth2/v1/me");

    const [, options] = mockFetch.mock.calls[0];
    expect((options.headers as Record<string, string>)["Authorization"]).toBeUndefined();
  });

  it("sends POST with JSON body", async () => {
    await themosisApi(session).post("/wp-json/auth2/v1/test", { foo: "bar" });

    const [, options] = mockFetch.mock.calls[0];
    expect(options.method).toBe("POST");
    expect(options.body).toBe(JSON.stringify({ foo: "bar" }));
  });

  it("sends DELETE request", async () => {
    await themosisApi(session).delete("/wp-json/auth2/v1/test");

    const [, options] = mockFetch.mock.calls[0];
    expect(options.method).toBe("DELETE");
  });
});
