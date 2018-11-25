import { sum } from "./math";

describe("This is a simple test", () => {
    test("Check the sum of 0 + 0", () => {
			expect(sum(0,0)).toBe(0);
    });
});

describe("This is a simple test", () => {
    test("Check the sum of 1 + 2", () => {
		expect(sum(1, 2)).toBe(3);    
	});
});
