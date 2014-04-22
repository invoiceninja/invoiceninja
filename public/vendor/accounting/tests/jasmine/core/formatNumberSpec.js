describe('formatNumber', function(){

    describe('rounding and enforce precision', function(){

        it('should enforce precision and round values', function(){
        
            expect( accounting.formatNumber(123.456789, 0) ).toBe( '123' );
            expect( accounting.formatNumber(123.456789, 1) ).toBe( '123.5' );
            expect( accounting.formatNumber(123.456789, 2) ).toBe( '123.46' );
            expect( accounting.formatNumber(123.456789, 3) ).toBe( '123.457' );
            expect( accounting.formatNumber(123.456789, 4) ).toBe( '123.4568' );
            expect( accounting.formatNumber(123.456789, 5) ).toBe( '123.45679' );

        });

        it('should fix floting point rounding error', function(){

            expect( accounting.formatNumber(0.615, 2) ).toBe( '0.62' );
            expect( accounting.formatNumber(0.614, 2) ).toBe( '0.61' );

        });

        it('should work for large numbers', function(){
            
            expect( accounting.formatNumber(123456.54321, 0) ).toBe( '123,457' );
            expect( accounting.formatNumber(123456.54321, 1) ).toBe( '123,456.5' );
            expect( accounting.formatNumber(123456.54321, 2) ).toBe( '123,456.54' );
            expect( accounting.formatNumber(123456.54321, 3) ).toBe( '123,456.543' );
            expect( accounting.formatNumber(123456.54321, 4) ).toBe( '123,456.5432' );
            expect( accounting.formatNumber(123456.54321, 5) ).toBe( '123,456.54321' );

            expect( accounting.formatNumber(98765432.12, 0) ).toBe( '98,765,432' );
            expect( accounting.formatNumber(98765432.12, 1) ).toBe( '98,765,432.1' );
            expect( accounting.formatNumber(98765432.12, 2) ).toBe( '98,765,432.12' );
            expect( accounting.formatNumber(98765432.12, 3) ).toBe( '98,765,432.120' );
            expect( accounting.formatNumber(98765432.12, 4) ).toBe( '98,765,432.1200' );

        });

        it('should work for negative numbers', function(){
            
            expect( accounting.formatNumber(-123456.54321, 0) ).toBe( '-123,457' );
            expect( accounting.formatNumber(-123456.54321, 1) ).toBe( '-123,456.5' );
            expect( accounting.formatNumber(-123456.54321, 2) ).toBe( '-123,456.54' );
            expect( accounting.formatNumber(-123456.54321, 3) ).toBe( '-123,456.543' );
            expect( accounting.formatNumber(-123456.54321, 4) ).toBe( '-123,456.5432' );
            expect( accounting.formatNumber(-123456.54321, 5) ).toBe( '-123,456.54321' );

            expect( accounting.formatNumber(-98765432.12, 0) ).toBe( '-98,765,432' );
            expect( accounting.formatNumber(-98765432.12, 1) ).toBe( '-98,765,432.1' );
            expect( accounting.formatNumber(-98765432.12, 2) ).toBe( '-98,765,432.12' );
            expect( accounting.formatNumber(-98765432.12, 3) ).toBe( '-98,765,432.120' );
            expect( accounting.formatNumber(-98765432.12, 4) ).toBe( '-98,765,432.1200' );

        });

    });


    describe('separators', function(){

        it('should allow setting thousands separator', function(){
            expect( accounting.formatNumber(98765432.12, 0, '|') ).toBe( '98|765|432' );
            expect( accounting.formatNumber(98765432.12, 1, '>') ).toBe( '98>765>432.1' );
            expect( accounting.formatNumber(98765432.12, 2, '*') ).toBe( '98*765*432.12' );
            expect( accounting.formatNumber(98765432.12, 3, '\'') ).toBe( '98\'765\'432.120' );
            expect( accounting.formatNumber(98765432.12, 4, ']') ).toBe( '98]765]432.1200' );
        });


        it('should allow setting decimal separator', function(){
            expect( accounting.formatNumber(98765432.12, 0, null, '|') ).toBe( '98,765,432' );
            expect( accounting.formatNumber(98765432.12, 1, null, '>') ).toBe( '98,765,432>1' );
            expect( accounting.formatNumber(98765432.12, 2, null, '*') ).toBe( '98,765,432*12' );
            expect( accounting.formatNumber(98765432.12, 3, null, '\'') ).toBe( '98,765,432\'120' );
            expect( accounting.formatNumber(98765432.12, 4, null, ']') ).toBe( '98,765,432]1200' );
        });

        it('should allow setting thousand and decimal separators', function(){
            expect( accounting.formatNumber(98765432.12, 0, '\\', '|') ).toBe( '98\\765\\432' );
            expect( accounting.formatNumber(98765432.12, 1, '<', '>') ).toBe( '98<765<432>1' );
            expect( accounting.formatNumber(98765432.12, 2, '&', '*') ).toBe( '98&765&432*12' );
            expect( accounting.formatNumber(98765432.12, 3, '"', '\'') ).toBe( '98"765"432\'120' );
            expect( accounting.formatNumber(98765432.12, 4, '[', ']') ).toBe( '98[765[432]1200' );
        });

        it('should use default separators if null', function(){
            expect( accounting.formatNumber(12345.12345, 2, null, null) ).toBe('12,345.12');
        });

        it('should use empty separators if passed as empty string', function(){
            expect( accounting.formatNumber(12345.12345, 2, '', '') ).toBe('1234512');
        });

    });


    describe('multiple numbers (array)', function(){
    
        it('should handle an array of numbers', function(){

            var vals = accounting.formatNumber([123, 456.78, 1234.123], 2);

            expect( vals[0] ).toBe( '123.00' );
            expect( vals[1] ).toBe( '456.78' );
            expect( vals[2] ).toBe( '1,234.12' );
        });

    });

    describe('properties object', function(){
    
        it('should accept a properties object', function(){

            var val = accounting.formatNumber(123456789.1234, {
                thousand : '.',
                decimal : ',',
                precision : 3
            });

            expect( val ).toBe( '123.456.789,123' );
        });

        it('properties should be optional', function(){
            var val = accounting.formatNumber(123456789.1234, {});
            expect( val ).toBe( '123,456,789' );
        });

    });


});
