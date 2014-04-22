describe('unformat()', function(){

    it('should remove padding special chars', function(){
        expect( accounting.unformat('$ 123,456') ).toBe( 123456 );
        expect( accounting.unformat('$ 123,456.78') ).toBe( 123456.78 );
        expect( accounting.unformat('&*()$ 123,456') ).toBe( 123456 );
        expect( accounting.unformat(';$@#$%^&123,456.78') ).toBe( 123456.78 );
    });

    it('should work with negative numbers', function(){
        expect( accounting.unformat('$ -123,456') ).toBe( -123456 );
        expect( accounting.unformat('$ -123,456.78') ).toBe( -123456.78 );
        expect( accounting.unformat('&*()$ -123,456') ).toBe( -123456 );
        expect( accounting.unformat(';$@#$%^&-123,456.78') ).toBe( -123456.78 );
    });
    
    it('should accept different decimal separators', function(){    
        expect( accounting.unformat('$ 123,456', ',') ).toBe( 123.456 );
        expect( accounting.unformat('$ 123456|78', '|') ).toBe( 123456.78 );
        expect( accounting.unformat('&*()$ 123>456', '>') ).toBe( 123.456 );
        expect( accounting.unformat(';$@#$%^&123,456\'78', '\'') ).toBe( 123456.78 );
    });

    it('should accept an array', function(){
        var vals = accounting.unformat(['$ 123', '$567.89', 'R$12,345,678.901']);
        expect( vals[0] ).toBe( 123 );
        expect( vals[1] ).toBe( 567.89 );
        expect( vals[2] ).toBe( 12345678.901 );
    });

});
