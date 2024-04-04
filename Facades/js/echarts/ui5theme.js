(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define(['exports', 'echarts'], factory);
    } else if (typeof exports === 'object' && typeof exports.nodeName !== 'string') {
        // CommonJS
        factory(exports, require('echarts'));
    } else {
        // Browser globals
        factory({}, root.echarts);
    }
}(this, function (exports, echarts) {
    var log = function (msg) {
        if (typeof console !== 'undefined') {
            console && console.error && console.error(msg);
        }
    };
    if (!echarts) {
        log('ECharts is not Loaded');
        return;
    }
    echarts.registerTheme('ui5theme', {
        color: [
    	'#c23531',
    	'#2f4554',
    	'#61a0a8',
    	'#d48265',
    	'#91c7ae',
    	'#749f83',
    	'#ca8622',
    	'#bda29a',
    	'#6e7074',
    	'#546570',
    	'#c4ccd3'
  ],
        "backgroundColor": "rgba(0,0,0,0)",
        "textStyle": {},
        "title": {
            "textStyle": {
                "color": "#005eaa"
            },
            "subtextStyle": {
                "color": "#005eaa"
            }
        },
        "line": {
            "itemStyle": {
                "borderWidth": 1
            },
            "lineStyle": {
                "normal": {
                    "width": 2
                }
            },
            "symbolSize": 4,
            "symbol": "emptyCircle",
            "smooth": false
        },
        "radar": {
            "itemStyle": {
                "borderWidth": 1
            },
            "lineStyle": {
                "normal": {
                    "width": 2
                }
            },
            "symbolSize": 4,
            "symbol": "emptyCircle",
            "smooth": false
        },
        "bar": {
            "itemStyle": {
                "barBorderWidth": 0,
                "barBorderColor": "#ccc"
            },
            "emphasis": {
				"itemStyle": {
					"barBorderWidth": 0,
                	"barBorderColor": "#ccc"
				}
            }
        },
        "pie": {
            "itemStyle": {
                "borderWidth": 0,
                "borderColor": "#ccc"
            },
            "emphasis": {
				"itemStyle": {
					"borderWidth": 0,
                	"borderColor": "#ccc"
				}
            }
        },
        "scatter": {
            "itemStyle": {
                "borderWidth": 0,
                "borderColor": "#ccc"
            },
            "emphasis": {
				"itemStyle": {
					"borderWidth": 0,
                "borderColor": "#ccc"
				}         
            }
        },
        "boxplot": {
            "itemStyle": {
                "borderWidth": 0,
                "borderColor": "#ccc"
            },
            "emphasis": {
				"itemStyle": {
					"borderWidth": 0,
                	"borderColor": "#ccc"
				}
            }
        },
        "parallel": {
            "itemStyle": {
                "borderWidth": 0,
                "borderColor": "#ccc"
            },
            "emphasis": {
				"itemStyle": {
					"borderWidth": 0,
                	"borderColor": "#ccc"
				}
            }
        },
        "sankey": {
            "itemStyle": {
                "borderWidth": 0,
                "borderColor": "#ccc"
            },
            "emphasis": {
				"itemStyle": {
					"borderWidth": 0,
                	"borderColor": "#ccc"
				}
            }
        },
        "funnel": {
            "itemStyle": {
                "borderWidth": 0,
                "borderColor": "#ccc"
            },
            "emphasis": {
				"itemStyle": {
					 "borderWidth": 0,
                	"borderColor": "#ccc"
				}
            }
        },
        "gauge": {
            "itemStyle": {
                "borderWidth": 0,
                "borderColor": "#ccc"
            },
            "emphasis": {
				"itemStyle": {
					"borderWidth": 0,
                	"borderColor": "#ccc"
				}
            }
        },
        "candlestick": {
            "itemStyle": {
                "color": "#c12e34",
                "color0": "#2b821d",
                "borderColor": "#c12e34",
                "borderColor0": "#2b821d",
                "borderWidth": 1
            }
        },
        "graph": {
            "itemStyle": {
                "borderWidth": 0,
                "borderColor": "#ccc"
            },
            "lineStyle": {
                "normal": {
                    "width": 1,
                    "color": "#aaaaaa"
                }
            },
            "symbolSize": 4,
            "symbol": "circle",
            "smooth": false,
            "color": [
                "#5899da",
                "#e8743b",
                "#19a979",
                "#ed4a7b",
                "#945ecf",
                "#13a4b4",
                "#525df4",
                "#bf399e",
                "#6c8893",
                "#ee6868",
                "#2f6497"
            ],
            "label": {
                "normal": {
                    "textStyle": {
                        "color": "#eeeeee"
                    }
                }
            }
        },
        "map": {
            "itemStyle": {
                "areaColor": "#dddddd",
                "borderColor": "#eeeeee",
                "borderWidth": 0.5
            },
            "emphasis": {
				"itemStyle": {
					"areaColor": "rgba(230,182,0,1)",
                	"borderColor": "#dddddd",
                	"borderWidth": 1
				} 
            },
            "label": {
                "normal": {
                    "textStyle": {
                        "color": "#c12e34"
                    }
                }
            },
            "emphasis": {
                "label": {
                    "color": "rgb(193,46,52)"
                }
            }
        },
        "geo": {
            "itemStyle": {
                "areaColor": "#dddddd",
                "borderColor": "#eeeeee",
                "borderWidth": 0.5
            },
            "emphasis": {
				"itemStyle": {
					"areaColor": "rgba(230,182,0,1)",
                	"borderColor": "#dddddd",
                	"borderWidth": 1
				}       
            },
            "label": {
                "color": "#c12e34"
            },
            "emphasis": {
                "label": {
                    "color": "rgb(193,46,52)"
                }
            }
        },
        "categoryAxis": {
            "axisLine": {
                "show": true,
                "lineStyle": {
                    "color": "#333"
                }
            },
            "axisTick": {
                "show": true,
                "lineStyle": {
                    "color": "#333"
                }
            },
            "axisLabel": {
                "show": true,
                "color": "#333"
            },
            "splitLine": {
                "show": false,
                "lineStyle": {
                    "color": [
                        "#ccc"
                    ]
                }
            },
            "splitArea": {
                "show": false,
                "areaStyle": {
                    "color": [
                        "rgba(250,250,250,0.3)",
                        "rgba(200,200,200,0.3)"
                    ]
                }
            }
        },
        "valueAxis": {
            "axisLine": {
                "show": true,
                "lineStyle": {
                    "color": "#333"
                }
            },
            "axisTick": {
                "show": true,
                "lineStyle": {
                    "color": "#333"
                }
            },
            "axisLabel": {
                "show": true,
                "color": "#333"
            },
            "splitLine": {
                "show": false,
                "lineStyle": {
                    "color": [
                        "#ccc"
                    ]
                }
            },
            "splitArea": {
                "show": false,
                "areaStyle": {
                    "color": [
                        "rgba(250,250,250,0.3)",
                        "rgba(200,200,200,0.3)"
                    ]
                }
            }
        },
        "logAxis": {
            "axisLine": {
                "show": true,
                "lineStyle": {
                    "color": "#333"
                }
            },
            "axisTick": {
                "show": true,
                "lineStyle": {
                    "color": "#333"
                }
            },
            "axisLabel": {
                "show": true,
                "color": "#333"
            },
            "splitLine": {
                "show": false,
                "lineStyle": {
                    "color": [
                        "#ccc"
                    ]
                }
            },
            "splitArea": {
                "show": false,
                "areaStyle": {
                    "color": [
                        "rgba(250,250,250,0.3)",
                        "rgba(200,200,200,0.3)"
                    ]
                }
            }
        },
        "timeAxis": {
            "axisLine": {
                "show": true,
                "lineStyle": {
                    "color": "#333"
                }
            },
            "axisTick": {
                "show": true,
                "lineStyle": {
                    "color": "#333"
                }
            },
            "axisLabel": {
                "show": true,
                "color": "#333"
            },
            "splitLine": {
                "show": false,
                "lineStyle": {
                    "color": [
                        "#ccc"
                    ]
                }
            },
            "splitArea": {
                "show": false,
                "areaStyle": {
                    "color": [
                        "rgba(250,250,250,0.3)",
                        "rgba(200,200,200,0.3)"
                    ]
                }
            }
        },
        "toolbox": {
            "iconStyle": {
        		"borderColor": "#06467c"
            },
            "emphasis": {
                "iconStyle": {
                    "borderColor": "#4187c2"
                }
            }
        },
        "legend": {
            "textStyle": {
                "color": "#333333"
            }
        },
        "tooltip": {
            "axisPointer": {
                "lineStyle": {
                    "color": "#cccccc",
                    "width": 1
                },
                "crossStyle": {
                    "color": "#cccccc",
                    "width": 1
                }
            }
        },
        "timeline": {
            "lineStyle": {
                "color": "#005eaa",
                "width": 1
            },
            "itemStyle": {
                "color": "#005eaa",
                "borderWidth": 1
            },
            "emphasis": {
				"itemStyle": {
					"color": "#005eaa"
				}
            },
            "emphasis": {
				"controlStyle": {
					"color": "#005eaa",
                    "borderColor": "#005eaa",
                    "borderWidth": 0.5
				}
			},
            "controlStyle": {
                "color": "#005eaa",
                "borderColor": "#005eaa",
                "borderWidth": 0.5
            },
            "checkpointStyle": {
                "color": "#005eaa",
                "borderColor": "rgba(49,107,194,0.5)"
            },
            "emphasis": {
				"label": {
                    "color": "#005eaa"
				}
			},
            "label": {
                "color": "#005eaa"
            }
        },
        "visualMap": {
            "color": [
                "#1790cf",
                "#a2d4e6"
            ]
        },
        "dataZoom": {
            "backgroundColor": "rgba(47,69,84,0)",
            "dataBackgroundColor": "rgba(47,69,84,0.3)",
            "fillerColor": "rgba(167,183,204,0.4)",
            "handleColor": "#a7b7cc",
            "handleSize": "100%",
            "textStyle": {
                "color": "#333333"
            }
        },
        "markPoint": {
            "label": {
                "color": "#eeeeee"
            },
            "emphasis": {
                    "label": {
                        "color": "#eeeeee"
                    }
                }
        }
    });
}));
