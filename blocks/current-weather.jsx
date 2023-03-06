const { registerBlockType } = wp.blocks;
const { withSelect } = wp.data;
import apiFetch from '@wordpress/api-fetch';

const WundergroundPWSDataBlock = ( { options, station_id } ) => {
    if ( options ) {
        return (
            <div>
                <h4>Current weather conditions from <a href={`https://www.wunderground.com/dashboard/pws/${ station_id }`} target='_blank'>{ station_id }</a></h4>
                <p><em>Last updated { options.obsTimeLocal }</em></p>
                <p>{ ( 'Temperature:' ) } { options.temp }&deg;F</p>
                <p>{ ( 'Humidity' ) } { options.humidity }%</p>
                <p>{ ( 'UV Index' ) } { options.uv }</p>
                <p>{ ( 'Heat Index:' ) } { options.heatIndex }&deg;F</p>
                <p>{ ( 'Dew Point:' ) } { options.dewpt }&deg;F</p>
                <p>{ ( 'Precipitation Rate:' ) } { options.precipRate } in/hr</p>
                <p>{ ( 'Total Precipitation:' ) } { options.precipTotal } in</p>
                <p>{ ( 'Wind Chill:' ) } { options.windChill }&deg;F</p>
                <p>{ ( 'Wind Speed:' ) } { options.windSpeed } mph</p>
                <p>{ ( 'Wind Gust:' ) } { options.windGust } mph</p>
                <p>{ ( 'Pressure:' ) } { options.pressure } inHg</p>
            </div>
        );
    } else {
        return <p>{ 'Data is not available.' }</p>;
    }
};

registerBlockType( 'wu-pws-blocks/current-weather', {
    title: 'Wunderground PWS Data',
    icon: 'cloud',
    category: 'widgets',
    edit: function( props ) {
        const [ options, setOptions ] = React.useState( null );
        const [ station_id, setStationId ] = React.useState( null );
        
        React.useEffect( () => {
            apiFetch( { path: '/wp-json/wu-pws/v1/current' } ).then( ( data ) => {
                setOptions( data );
            });
            
            apiFetch( { path: '/wp-json/wu-pws/v1/station_id' } ).then( ( data ) => {
                setStationId( data );
            });
        }, [] );
        
        return <WundergroundPWSDataBlock options={ options } station_id={ station_id } />;
    },
    save: () => {
        return <WundergroundPWSDataBlock />;
    },
} );

