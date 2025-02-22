var analytics = {
    view: function (entity, id, token) {
        var path = 'api_1_' + entity + '_view';
        var url = Routing.generate(path, {
            id: id,
            _format: 'json'
        }, true);
        var data = {
            token: token
        };
        $.ajax({
            url: url,
            data: data,
            method: 'POST'
        });
    },

    createChart: function (id, column) {
        var chart = c3.generate({
            bindto: id,
            data: {
                x: 'x',
                columns: [data['dates'], column],
                type: 'bar'
            },
            subchart: {
                show: false
            },
            zoom: {
                enabled: true
            },
            axis: {
                x: {
                    type: 'timeseries',
                    tick: {
                        format: function (x) {
                            return x.toLocaleDateString();
                        },
                        culling: {
                            max: 8
                        }
                    }
                }
            },
            bar: {
                width: {
                    ratio: 0.6
                }
            }
        });
    },

    createPieChart: function (id, columns) {
        var newNames = {};
        columns['mainChartNames'].forEach(function (article) {
            newNames[article[0]] = article[1];
        });

        for (var key in columns['charts']) {
            columns['charts'][key].forEach(function (file) {
                newNames[file[2]] = file[0];
                file[0] = file[2];
                file.splice(2);
            });
        }

        var newDatas = columns['charts'];
        newDatas['mainChart'] = columns['mainChart'];

        var chart = c3.generate({
            bindto: id,
            data: {
                columns: newDatas['mainChart'],
                type: 'pie',
                onclick: function (d, i) {
                    if (newDatas[d.id]) {
                        chart.load({
                            unload: true,
                            columns: newDatas[d.id]
                        });
                    } else {
                        chart.load({
                            unload: true,
                            columns: newDatas['mainChart']
                        });
                    }
                }
            },
            tooltip: {
                format: {
                    value: function (x) {
                        return x;
                    }
                }
            }
        });

        chart.data.names(newNames);
    }
};