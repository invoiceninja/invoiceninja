Cypress.Laravel = {
    routes: {},

    route: (name, parameters = {}) => {
        assert(
            Cypress.Laravel.routes.hasOwnProperty(name),
            `Laravel route "${name}" does not exist.`
        );

        return ((uri) => {
            Object.keys(parameters).forEach((parameter) => {
                uri = uri.replace(
                    new RegExp(`{${parameter}}`),
                    parameters[parameter]
                );
            });

            return uri;
        })(Cypress.Laravel.routes[name].uri);
    },
};
